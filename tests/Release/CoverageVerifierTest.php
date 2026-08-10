<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\CoverageVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/CoverageVerifier.php';

final class CoverageVerifierTest extends TestCase
{
    private Filesystem $filesystem;
    private string $root;
    private CoverageVerifier $verifier;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/php-upgrade-preflight-coverage-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->root . '/src');
        $this->filesystem->dumpFile(
            $this->root . '/src/Critical.php',
            "<?php\n\$covered = true;\n\$uncovered = false;\n"
        );
        $this->verifier = new CoverageVerifier($this->root, ['src/Critical.php']);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
    }

    public function testMeasuresCloverAndAcceptsItsOwnBaseline(): void
    {
        $clover = $this->writeClover([2 => 1, 3 => 0]);

        $measurement = $this->verifier->measure($clover);
        $baseline = ['schema_version' => 1] + $measurement;
        $this->verifier->verify($baseline, $measurement);

        self::assertSame(['covered' => 1, 'executable' => 2], $measurement['overall']);
        self::assertSame(
            ['covered' => 1, 'executable' => 2],
            $measurement['critical_modules']['src/Critical.php']
        );
        $fingerprint = hash('sha256', '$uncovered = false;');
        self::assertSame(1, $measurement['known_uncovered_fingerprints']['src/Critical.php'][$fingerprint]);
    }

    public function testWritesAndReadsBaseline(): void
    {
        $measurement = $this->verifier->measure($this->writeClover([2 => 1, 3 => 0]));
        $path = $this->root . '/nested/coverage-baseline.json';

        $this->verifier->writeBaseline($path, $measurement);
        $baseline = $this->verifier->readBaseline($path);

        self::assertSame(1, $baseline['schema_version']);
        self::assertSame($measurement['overall'], $baseline['overall']);
        self::assertContains('new_executable_lines_must_be_covered', $baseline['policy']);
    }

    public function testRejectsCoverageRatioDecrease(): void
    {
        $baseline = $this->measurement(2, 2);
        $current = $this->measurement(1, 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Coverage decreased for overall from 2/2 to 1/2 lines.');

        $this->verifier->verify($baseline, $current);
    }

    public function testRejectsCriticalModuleCoverageDecrease(): void
    {
        $baseline = $this->measurement(2, 2);
        $current = $this->measurement(2, 2);
        $current['critical_modules']['src/Critical.php'] = ['covered' => 1, 'executable' => 2];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Coverage decreased for src/Critical.php');

        $this->verifier->verify($baseline, $current);
    }

    public function testRejectsNewUncoveredFingerprint(): void
    {
        $baseline = $this->measurement(1, 2);
        $current = $this->measurement(1, 2);
        $current['known_uncovered_fingerprints'] = ['src/Critical.php' => ['new-fingerprint' => 1]];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('New or changed executable lines lack coverage');

        $this->verifier->verify($baseline, $current);
    }

    public function testRejectsCriticalModuleMissingFromClover(): void
    {
        $this->filesystem->dumpFile(
            $this->root . '/empty-clover.xml',
            '<?xml version="1.0"?><coverage><project/></coverage>'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Critical module "src/Critical.php" is absent');

        $this->verifier->measure($this->root . '/empty-clover.xml');
    }

    public function testRejectsUnreadableClover(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not read Clover report');

        $this->verifier->measure($this->root . '/missing.xml');
    }

    public function testRejectsMissingAndInvalidBaselines(): void
    {
        try {
            $this->verifier->readBaseline($this->root . '/missing-baseline.json');
            self::fail('Missing baseline was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Coverage baseline is missing', $exception->getMessage());
        }

        $this->filesystem->dumpFile($this->root . '/invalid-baseline.json', '{"schema_version":2}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unsupported schema');
        $this->verifier->readBaseline($this->root . '/invalid-baseline.json');
    }

    /** @dataProvider invalidCoverageDataProvider */
    public function testRejectsMalformedCoverageData(string $case, string $expectedMessage): void
    {
        $baseline = $this->measurement(1, 1);
        $current = $this->measurement(1, 1);

        if ($case === 'baseline-ratio') {
            $baseline['overall'] = 'invalid';
        } elseif ($case === 'critical-map') {
            $baseline['critical_modules'] = 'invalid';
        } elseif ($case === 'fingerprint-map') {
            $current['known_uncovered_fingerprints'] = 'invalid';
        } elseif ($case === 'fingerprint-file') {
            $current['known_uncovered_fingerprints'] = ['src/Critical.php' => 'invalid'];
        } elseif ($case === 'fingerprint-count') {
            $current['known_uncovered_fingerprints'] = ['src/Critical.php' => ['hash' => 'invalid']];
        } elseif ($case === 'empty-scope') {
            $current['overall'] = ['covered' => 0, 'executable' => 0];
        } else {
            self::fail('Unknown malformed coverage case.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->verifier->verify($baseline, $current);
    }

    /** @return list<array{string, string}> */
    public function invalidCoverageDataProvider(): array
    {
        return [
            ['baseline-ratio', 'Coverage ratio for baseline overall is invalid.'],
            ['critical-map', 'Coverage data for baseline critical modules is invalid.'],
            ['fingerprint-map', 'Coverage data for current uncovered fingerprints is invalid.'],
            ['fingerprint-file', 'Coverage data for current uncovered fingerprints is invalid.'],
            ['fingerprint-count', 'Coverage data for current uncovered fingerprints is invalid.'],
            ['empty-scope', 'Coverage scope "overall" has no executable lines.'],
        ];
    }

    /** @param array<int, int> $lineCounts */
    private function writeClover(array $lineCounts): string
    {
        $lines = '';
        foreach ($lineCounts as $line => $count) {
            $lines .= sprintf('<line num="%d" type="stmt" count="%d"/>', $line, $count);
        }
        $source = htmlspecialchars($this->root . '/src/Critical.php', ENT_QUOTES | ENT_XML1);
        $xml = sprintf(
            '<?xml version="1.0"?><coverage><project><file name="%s"><class/>%s</file></project></coverage>',
            $source,
            $lines
        );
        $path = $this->root . '/clover.xml';
        $this->filesystem->dumpFile($path, $xml);

        return $path;
    }

    /**
     * @return array{
     *   overall: array{covered: int, executable: int},
     *   critical_modules: array<string, array{covered: int, executable: int}>,
     *   known_uncovered_fingerprints: array<string, array<string, int>>
     * }
     */
    private function measurement(int $covered, int $executable): array
    {
        return [
            'overall' => ['covered' => $covered, 'executable' => $executable],
            'critical_modules' => [
                'src/Critical.php' => ['covered' => $covered, 'executable' => $executable],
            ],
            'known_uncovered_fingerprints' => [],
        ];
    }
}
