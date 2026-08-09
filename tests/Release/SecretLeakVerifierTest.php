<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\SecretLeakVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/SecretLeakVerifier.php';

final class SecretLeakVerifierTest extends TestCase
{
    private Filesystem $filesystem;
    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/php-upgrade-preflight-leak-scan-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testItAcceptsCleanFilesAndZipEntries(): void
    {
        $file = $this->directory . '/report.json';
        $zip = $this->directory . '/release.zip';
        $this->filesystem->dumpFile($file, '{"status":"clean"}');
        $archive = $this->openArchive($zip);
        $archive->addFromString('composer.json', '{"name":"fixture/package"}');
        $archive->close();

        self::assertSame([], $this->verifier()->verify([$file, $zip]));
    }

    public function testItFindsCanariesInTextZipContentsAndZipEntryNamesWithoutEchoingValues(): void
    {
        $canaries = $this->canaries();
        $labels = array_keys($canaries);
        $values = array_values($canaries);
        $file = $this->directory . '/report.json';
        $secretNamedFile = $this->directory . '/' . $values[3] . '.log';
        $zip = $this->directory . '/release.zip';
        $this->filesystem->dumpFile($file, $values[0]);
        $this->filesystem->dumpFile($secretNamedFile, 'ordinary content');
        $archive = $this->openArchive($zip);
        $archive->addFromString('payload.txt', $values[1]);
        $archive->addFromString('docs/' . $values[2] . '.txt', 'ordinary content');
        $archive->close();

        $errors = $this->verifier()->verify([$file, $secretNamedFile, $zip]);
        $message = implode("\n", $errors);

        self::assertNotSame([], $errors);
        foreach (array_slice($labels, 0, 4) as $label) {
            self::assertStringContainsString($label, $message);
        }
        foreach ($values as $value) {
            if (str_contains($message, $value)) {
                self::fail('Secret leak verifier exposed a matched canary value.');
            }
        }
    }

    private function verifier(): SecretLeakVerifier
    {
        return new SecretLeakVerifier($this->canaries());
    }

    /** @return array<string, string> */
    private function canaries(): array
    {
        $contents = file_get_contents(dirname(__DIR__) . '/fixtures/security/composer-output-with-secrets.json');
        self::assertNotFalse($contents);
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertIsArray($fixture['canaries'] ?? null);

        /** @var array<string, string> $canaries */
        $canaries = $fixture['canaries'];

        return $canaries;
    }

    private function openArchive(string $path): \ZipArchive
    {
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));

        return $archive;
    }
}
