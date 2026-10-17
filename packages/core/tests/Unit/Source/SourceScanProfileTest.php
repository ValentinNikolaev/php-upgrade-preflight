<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PhpUpgradePreflight\Core\Source\SourceScanProfile;
use PhpUpgradePreflight\Core\Source\SourceScanLimits;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SourceScanProfileTest extends TestCase
{
    private string $temporary;
    private ProjectState $project;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/source-profile-' . bin2hex(random_bytes(8));
        $path = $this->temporary . '/project';
        mkdir($path . '/src', 0700, true);
        file_put_contents($path . '/composer.json', '{"require":{}}');
        file_put_contents($path . '/composer.lock', '{"packages":[],"packages-dev":[]}');
        file_put_contents($path . '/src/One.php', '<?php Acme\\One::run();');
        $this->project = (new ProjectStateBuilder())->build($path);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    public function testColdAndWarmCountersReflectOperationsAndResetWithIdenticalFacts(): void
    {
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($this->temporary . '/cache'), $profile);
        $first = $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $cold = $profile->snapshot();
        self::assertSame(1, $cold['counters']['selected_files']);
        self::assertSame(1, $cold['counters']['read_files']);
        self::assertSame(1, $cold['counters']['parsed_files']);
        self::assertSame(1, $cold['counters']['cache_misses']);
        self::assertSame(1, $cold['counters']['cache_stored_entries']);
        self::assertEquals($first, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        $warm = $profile->snapshot();
        self::assertSame(1, $warm['counters']['selected_files']);
        self::assertSame(1, $warm['counters']['read_files']);
        self::assertSame(0, $warm['counters']['parse_attempts']);
        self::assertSame(1, $warm['counters']['cache_hits']);
        self::assertSame(0, $warm['counters']['cache_put_attempts']);
        self::assertSame(0, $warm['counters']['cache_misses']);
        self::assertGreaterThanOrEqual(array_sum($warm['timings_ms']), $warm['total_ms']);
        self::assertSame($profile, $scanner->profile());
        self::assertStringNotContainsString($this->temporary, (string) json_encode($warm));
    }

    public function testCorruptAndOversizedEntriesAreRejectedBeforeRealParsingFallback(): void
    {
        $profile = new SourceScanProfile();
        $directory = $this->temporary . '/cache';
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($directory), $profile);
        $expected = $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $entries = glob($directory . '/*.json') ?: [];
        self::assertCount(1, $entries);
        file_put_contents($entries[0], '{broken');
        self::assertEquals($expected, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        $corrupt = $profile->snapshot()['counters'];
        self::assertSame(1, $corrupt['cache_rejected_entries']);
        self::assertSame(1, $corrupt['cache_misses']);
        self::assertSame(1, $corrupt['parsed_files']);
        self::assertSame(0, $corrupt['cache_hits']);
        $entries = glob($directory . '/*.json') ?: [];
        foreach ($entries as $entry) {
            file_put_contents($entry, str_repeat('x', 8193));
        }
        self::assertEquals($expected, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        $oversized = $profile->snapshot()['counters'];
        self::assertSame(count($entries), $oversized['cache_read_oversized_entries']);
        self::assertSame(count($entries), $oversized['cache_rejected_entries']);
        self::assertSame(1, $oversized['parse_attempts']);
    }

    public function testLargeFactsAreReturnedButOversizedCacheWritesAreNotStored(): void
    {
        $code = '<?php ';
        for ($index = 0; $index < 200; ++$index) {
            $code .= '\\Acme\\LongClassNameForOversizedSourceFacts' . $index . '::run();';
        }
        file_put_contents($this->project->path() . '/src/One.php', $code);
        $profile = new SourceScanProfile();
        $directory = $this->temporary . '/cache';
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($directory), $profile);
        self::assertCount(200, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        $counters = $profile->snapshot()['counters'];
        self::assertSame(1, $counters['parsed_files']);
        self::assertSame(1, $counters['cache_put_attempts']);
        self::assertSame(1, $counters['cache_write_oversized_entries']);
        self::assertSame(1, $counters['cache_put_failures']);
        self::assertSame(0, $counters['cache_stored_entries']);
        self::assertDirectoryDoesNotExist($directory);
        self::assertStringNotContainsString('LongClassName', (string) json_encode($profile->snapshot()));
    }

    public function testParseFailureCountsAttemptsWithoutSuccessfulParsingOrWriting(): void
    {
        file_put_contents($this->project->path() . '/src/One.php', '<?php class {');
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($this->temporary . '/cache'), $profile);
        $evidence = new EvidenceLedger();
        $uncertainties = [];
        self::assertSame([], $scanner->scan($this->project, ['src'], $evidence, $uncertainties));
        $counters = $profile->snapshot()['counters'];
        self::assertSame(1, $counters['read_files']);
        self::assertSame(1, $counters['parse_attempts']);
        self::assertSame(1, $counters['parse_failures']);
        self::assertSame(0, $counters['parsed_files']);
        self::assertSame(0, $counters['cache_put_attempts']);
        self::assertSame('parse_error', $evidence->all()[0]->toArray()['context']['failure_type']);
    }

    public function testReadCountsIncludeOversizeProbeBytesWithoutClaimingParsing(): void
    {
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner(null, new SourceScanLimits(maxFileBytes: 4), null, $profile);
        $uncertainties = [];
        self::assertSame([], $scanner->scan($this->project, ['src'], new EvidenceLedger(), $uncertainties));
        $counters = $profile->snapshot()['counters'];
        self::assertSame(1, $counters['selected_files']);
        self::assertSame(1, $counters['read_attempts']);
        self::assertSame(1, $counters['read_files']);
        self::assertSame(5, $counters['read_bytes']);
        self::assertSame(0, $counters['parse_attempts']);
        self::assertStringContainsString('per-file byte limit', implode(' ', $uncertainties));
    }

    public function testProfilingIsOptionalAndEmptyScansResetEveryOperationCount(): void
    {
        $plain = new SourceUsageScanner();
        self::assertNull($plain->profile());
        $expected = $plain->scan($this->project, ['src'], new EvidenceLedger());
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner(null, null, null, $profile);
        self::assertEquals($expected, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        self::assertSame(1, $profile->snapshot()['counters']['cache_bypassed_files']);
        self::assertSame(0, $profile->snapshot()['counters']['cache_lookups']);
        mkdir($this->project->path() . '/empty');
        self::assertSame([], $scanner->scan($this->project, ['empty'], new EvidenceLedger()));
        self::assertSame([0], array_values(array_unique($profile->snapshot()['counters'])));
    }

    public function testUnknownParserIdentityBypassesCacheEvenWithProfiling(): void
    {
        $parser = $this->createMock(\PhpParser\Parser::class);
        $parser->expects(self::exactly(2))->method('parse')->willReturn([]);
        $profile = new SourceScanProfile();
        $directory = $this->temporary . '/cache';
        $scanner = new SourceUsageScanner($parser, null, new SourceFactsCache($directory), $profile);
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $counters = $profile->snapshot()['counters'];
        self::assertSame(1, $counters['parse_attempts']);
        self::assertSame(1, $counters['parsed_files']);
        self::assertSame(1, $counters['cache_bypassed_files']);
        self::assertSame(0, $counters['cache_lookups']);
        self::assertDirectoryDoesNotExist($directory);
    }

    public function testUnexpectedParserFailureStillFinishesThePartialProfile(): void
    {
        $parser = $this->createMock(\PhpParser\Parser::class);
        $parser->method('parse')->willThrowException(new \RuntimeException('Injected parser failure.'));
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner($parser, null, null, $profile);
        try {
            $scanner->scan($this->project, ['src'], new EvidenceLedger());
            self::fail('The original unexpected exception must propagate.');
        } catch (\RuntimeException $failure) {
            self::assertSame('Injected parser failure.', $failure->getMessage());
        }
        $snapshot = $profile->snapshot();
        self::assertSame(1, $snapshot['counters']['parse_attempts']);
        self::assertSame(1, $snapshot['counters']['parse_failures']);
        self::assertSame(0, $snapshot['counters']['parsed_files']);
        self::assertGreaterThanOrEqual(array_sum($snapshot['timings_ms']), $snapshot['total_ms']);
    }

    public function testUnsafeCacheDestinationIsUnavailableWithoutWritingIntoTheProject(): void
    {
        $profile = new SourceScanProfile();
        $directory = $this->project->path() . '/unsafe-cache';
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($directory), $profile);
        self::assertCount(1, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        $counters = $profile->snapshot()['counters'];
        self::assertSame(1, $counters['cache_unavailable_lookups']);
        self::assertSame(0, $counters['cache_rejected_entries']);
        self::assertSame(1, $counters['cache_misses']);
        self::assertSame(1, $counters['parsed_files']);
        self::assertSame(1, $counters['cache_put_failures']);
        self::assertSame(0, $counters['cache_stored_entries']);
        self::assertDirectoryDoesNotExist($directory);
    }

    public function testASelectedFileDisappearingBeforeReadIsNotCountedAsReadOrParsed(): void
    {
        $second = $this->project->path() . '/src/Two.php';
        file_put_contents($second, '<?php Acme\\Two::run();');
        $parser = $this->createMock(\PhpParser\Parser::class);
        $parser->expects(self::once())->method('parse')->willReturnCallback(static function () use ($second): array {
            unlink($second);

            return [];
        });
        $profile = new SourceScanProfile();
        $scanner = new SourceUsageScanner($parser, null, null, $profile);
        $uncertainties = [];
        $scanner->scan($this->project, ['src'], new EvidenceLedger(), $uncertainties);
        $counters = $profile->snapshot()['counters'];
        self::assertSame(2, $counters['selected_files']);
        self::assertSame(2, $counters['read_attempts']);
        self::assertSame(1, $counters['read_files']);
        self::assertSame(1, $counters['read_failures']);
        self::assertSame(1, $counters['parsed_files']);
        self::assertStringContainsString('could not be read', implode(' ', $uncertainties));
    }

    public function testProfilingPreservesEvidenceCapsUncertaintiesAndExactReadDigests(): void
    {
        file_put_contents($this->project->path() . '/src/Two.php', '<?php Acme\\Two::run();');
        $limits = new SourceScanLimits(maxUsages: 1);
        $plainLedger = new EvidenceLedger();
        $plainWarnings = [];
        $plainDigests = [];
        $expected = (new SourceUsageScanner(null, $limits))->scan($this->project, ['src'], $plainLedger, $plainWarnings, true, [], $plainDigests);
        $profile = new SourceScanProfile();
        $ledger = new EvidenceLedger();
        $warnings = [];
        $digests = [];
        $actual = (new SourceUsageScanner(null, $limits, null, $profile))->scan($this->project, ['src'], $ledger, $warnings, true, [], $digests);
        self::assertEquals($expected, $actual);
        self::assertEquals($plainLedger->all(), $ledger->all());
        self::assertSame($plainWarnings, $warnings);
        self::assertSame($plainDigests, $digests);
        self::assertCount(1, $actual);
        self::assertSame(2, $profile->snapshot()['counters']['parsed_files'], 'Parsed-file counts cannot be derived from the capped facts.');
    }

    public function testSymlinkedEntryIsCountedAsRejectedWithoutChangingItsTarget(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Requires unprivileged Unix symlinks.');
        }
        $profile = new SourceScanProfile();
        $directory = $this->temporary . '/cache';
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($directory), $profile);
        $expected = $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $entries = glob($directory . '/*.json') ?: [];
        self::assertCount(1, $entries);
        $target = $this->project->path() . '/src/One.php';
        $digest = hash_file('sha256', $target);
        unlink($entries[0]);
        self::assertTrue(symlink($target, $entries[0]));
        self::assertEquals($expected, $scanner->scan($this->project, ['src'], new EvidenceLedger()));
        self::assertSame(1, $profile->snapshot()['counters']['cache_rejected_entries']);
        self::assertSame(1, $profile->snapshot()['counters']['parsed_files']);
        self::assertSame($digest, hash_file('sha256', $target));
    }
}
