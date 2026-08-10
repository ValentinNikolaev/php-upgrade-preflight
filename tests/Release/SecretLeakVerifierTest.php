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

    public function testItLoadsCanariesFromFixture(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/security/composer-output-with-secrets.json';
        $canaries = $this->canaries();
        $file = $this->directory . '/report.txt';
        $this->filesystem->dumpFile($file, reset($canaries));

        $errors = SecretLeakVerifier::fromFixture($fixture)->verify([$file]);

        self::assertNotSame([], $errors);
        self::assertStringContainsString((string) array_key_first($canaries), implode("\n", $errors));
    }

    public function testItRejectsMissingAndMalformedFixtures(): void
    {
        try {
            SecretLeakVerifier::fromFixture($this->directory . '/missing.json');
            self::fail('Missing canary fixture was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Could not read', $exception->getMessage());
        }

        $path = $this->directory . '/malformed.json';
        $this->filesystem->dumpFile($path, '{"canaries":"invalid"}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no canary map');
        SecretLeakVerifier::fromFixture($path);
    }

    /** @dataProvider invalidCanaryProvider */
    public function testItRejectsInvalidCanaries(array $canaries): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('labels and values must be non-empty strings');

        new SecretLeakVerifier($canaries);
    }

    /** @return list<array{array<mixed, mixed>}> */
    public function invalidCanaryProvider(): array
    {
        return [
            [['' => 'value']],
            [['label' => '']],
            [[0 => 'value']],
        ];
    }

    public function testItScansDirectoriesAndReportsMissingInputs(): void
    {
        $canaries = $this->canaries();
        $label = (string) array_key_first($canaries);
        $this->filesystem->dumpFile($this->directory . '/nested/report.txt', reset($canaries));

        $errors = $this->verifier()->verify([
            $this->directory . '/nested',
            $this->directory . '/missing.txt',
        ]);
        $message = implode("\n", $errors);

        self::assertStringContainsString($label, $message);
        self::assertStringContainsString('Leak-scan input #2 does not exist.', $message);
    }

    public function testItRejectsMalformedZipArchives(): void
    {
        $zip = $this->directory . '/malformed.zip';
        $this->filesystem->dumpFile($zip, 'not a zip archive');

        $errors = $this->verifier()->verify([$zip]);

        self::assertSame(['Could not open release archive input #1 for leak scanning.'], $errors);
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
