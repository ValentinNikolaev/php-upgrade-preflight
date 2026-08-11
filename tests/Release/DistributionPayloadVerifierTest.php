<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\DistributionPayloadVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/DistributionPayloadVerifier.php';

final class DistributionPayloadVerifierTest extends TestCase
{
    private Filesystem $filesystem;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/preflight-distribution-payload-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir([$this->root . '/expected/src', $this->root . '/actual/src']);
        $this->filesystem->dumpFile($this->root . '/expected/composer.json', "{}\n");
        $this->filesystem->dumpFile($this->root . '/expected/src/Package.php', "<?php\n");
        $this->filesystem->mirror($this->root . '/expected', $this->root . '/actual', null, ['override' => true]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
        parent::tearDown();
    }

    public function testAcceptsIdenticalPayloads(): void
    {
        self::assertSame([], $this->verifier()->verify(
            $this->root . '/expected',
            $this->root . '/actual'
        ));
    }

    public function testRejectsMissingUnexpectedAndChangedFiles(): void
    {
        $this->filesystem->remove($this->root . '/actual/composer.json');
        $this->filesystem->dumpFile($this->root . '/actual/src/Package.php', "<?php\n// changed\n");
        $this->filesystem->dumpFile($this->root . '/actual/extra.txt', "unexpected\n");

        self::assertSame([
            'Distribution payload contains unexpected file extra.txt.',
            'Distribution payload differs at src/Package.php.',
            'Distribution payload is missing composer.json.',
        ], $this->verifier()->verify($this->root . '/expected', $this->root . '/actual'));
    }

    public function testRejectsMissingDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Distribution directory does not exist');

        $this->verifier()->verify($this->root . '/missing', $this->root . '/actual');
    }

    private function verifier(): DistributionPayloadVerifier
    {
        return new DistributionPayloadVerifier();
    }
}
