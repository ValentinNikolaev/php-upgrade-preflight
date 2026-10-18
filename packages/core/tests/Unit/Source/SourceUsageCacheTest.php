<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpParser\ErrorHandler;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Analysis\InputConsistencyTracker;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\SourceUsageVisitorProvider;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PhpUpgradePreflight\Core\Source\SourceUsageCacheKeyProvider;
use PhpUpgradePreflight\Core\Source\SourceUsageCollector;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SourceUsageCacheTest extends TestCase
{
    private string $temporary;
    private ProjectState $project;
    private CountingFactsParser $parser;
    private SourceFactsCache $cache;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/source-usage-cache-test-' . bin2hex(random_bytes(8));
        $path = $this->temporary . '/project';
        mkdir($path . '/src', 0700, true);
        file_put_contents($path . '/composer.json', '{"require":{}}');
        file_put_contents($path . '/composer.lock', '{"packages":[],"packages-dev":[]}');
        file_put_contents($path . '/src/Example.php', '<?php Acme\\First::run();');
        $this->project = (new ProjectStateBuilder())->build($path);
        $this->parser = new CountingFactsParser();
        $this->cache = new SourceFactsCache($this->temporary . '/cache');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    public function testSecondScanReusesPersistedFactsAndReconstructsCanonicalEvidence(): void
    {
        $before = $this->treeDigests();
        $cold = $this->scan();
        self::assertSame(1, $this->parser->calls);
        self::assertSame($cold, $this->scan());
        self::assertSame(1, $this->parser->calls, 'An unchanged second scan must avoid parsing.');
        $this->cache = SourceFactsCache::disabled();
        self::assertSame($cold, $this->scan());
        self::assertSame(2, $this->parser->calls);
        self::assertSame($before, $this->treeDigests(), 'Neither cache state nor scanning may modify the target.');
    }

    public function testDefaultScannerDoesNotPersistOrReuseFacts(): void
    {
        $scanner = new SourceUsageScanner($this->parser);
        $first = $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $second = $scanner->scan($this->project, ['src'], new EvidenceLedger());
        self::assertEquals($first, $second);
        self::assertSame(2, $this->parser->calls, 'Caching requires explicit opt-in.');
    }

    public function testRequestOptInReusesFactsWithoutLeakingIntoTheNextRequestOrCanonicalData(): void
    {
        $runner = new ComposerScenarioRunner(null, null, static fn (): array => ['exit_code' => 0, 'stdout' => 'Resolved.', 'stderr' => '']);
        $scanner = new SourceUsageScanner($this->parser);
        $analyzer = new DefaultUpgradeAnalyzer(scenarioRunner: $runner, sourceUsageScanner: $scanner);
        $directory = $this->temporary . '/opt-in';
        $request = new UpgradeRequest($this->project->path(), [], null, '8.2', ['src']);
        $cached = new UpgradeRequest($this->project->path(), [], null, '8.2', ['src'], sourceCacheDirectory: $directory);
        self::assertSame($request->toArray(), $cached->toArray());
        $first = $analyzer->analyzeUpgrade($cached);
        $second = $analyzer->analyzeUpgrade($cached);
        self::assertEquals($first->sourceInventory(), $second->sourceInventory());
        self::assertSame(1, $this->parser->calls);
        self::assertNotEmpty(glob($directory . '/*.json'));
        $analyzer->analyzeUpgrade($request);
        self::assertSame(2, $this->parser->calls, 'An opt-in request must not enable the analyzer cache for later default requests.');
    }

    public function testExactByteEditsAdditionsDeletionsAndRelativePathsAreObserved(): void
    {
        $this->scan();
        $path = $this->project->path() . '/src/Example.php';
        $mtime = filemtime($path);
        file_put_contents($path, '<?php Acme\\Other::run();');
        touch($path, $mtime);
        $edited = $this->scan();
        self::assertSame('Acme\\Other', $edited['usages'][0]['symbol']);
        self::assertSame(2, $this->parser->calls);
        copy($path, $this->project->path() . '/src/Added.php');
        self::assertCount(2, $this->scan()['usages']);
        self::assertSame(3, $this->parser->calls);
        unlink($path);
        $deleted = $this->scan();
        self::assertCount(1, $deleted['usages']);
        self::assertSame('src/Added.php', $deleted['usages'][0]['file']);
        self::assertSame(3, $this->parser->calls);
    }

    public function testWarmFactsAllocateEvidenceAgainstTheCurrentLedger(): void
    {
        $this->scan();
        $ledger = new EvidenceLedger();
        $ledger->add('source', Evidence::E3_PROJECT_SOURCE, 'An earlier source observation.');
        $usages = (new SourceUsageScanner($this->parser, null, $this->cache))->scan($this->project, ['src'], $ledger);
        self::assertSame(['source-2'], $usages[0]->evidence());
        self::assertSame('source-2', $ledger->all()[1]->id());
        self::assertSame(1, $this->parser->calls);
    }

    public function testProjectIdentityIsPartOfTheCacheKey(): void
    {
        $first = $this->scan();
        $other = $this->temporary . '/other';
        (new Filesystem())->mirror($this->project->path(), $other);
        $this->project = (new ProjectStateBuilder())->build($other);
        self::assertSame($first['usages'], $this->scan()['usages']);
        self::assertSame(2, $this->parser->calls, 'Identical relative paths and bytes in another project cannot reuse private facts.');
    }

    public function testParseFailureIsRetriedWithFreshEvidenceAndCannotPoisonARepair(): void
    {
        file_put_contents($this->project->path() . '/src/Example.php', '<?php class {');
        $failed = $this->scan();
        self::assertSame([], $failed['usages']);
        self::assertSame('parse_error', $failed['evidence'][0]['context']['failure_type']);
        self::assertSame($failed, $this->scan());
        self::assertSame(2, $this->parser->calls);
        self::assertSame([], glob($this->temporary . '/cache/*.json') ?: []);
        file_put_contents($this->project->path() . '/src/Example.php', '<?php Acme\\Repaired::run();');
        self::assertSame('Acme\\Repaired', $this->scan()['usages'][0]['symbol']);
    }

    public function testCorruptionAndUnavailabilityFallBackToTheSameFacts(): void
    {
        $cold = $this->scan();
        foreach (glob($this->temporary . '/cache/*.json') ?: [] as $file) {
            file_put_contents($file, '{broken');
        }
        self::assertSame($cold, $this->scan());
        self::assertSame(2, $this->parser->calls);
        $unavailable = $this->temporary . '/unavailable';
        file_put_contents($unavailable, 'a regular file');
        $this->cache = new SourceFactsCache($unavailable);
        self::assertSame($cold, $this->scan());
        self::assertSame(3, $this->parser->calls);
    }

    public function testCollectorIdentityAndConfigurationChangesInvalidateFacts(): void
    {
        $provider = new CacheAwareFactsProvider();
        $first = $this->scan([$provider]);
        self::assertSame($first, $this->scan([$provider]));
        self::assertSame(1, $this->parser->calls);
        $provider->configuration = 'changed';
        $changed = $this->scan([$provider]);
        self::assertSame('changed', $changed['usages'][1]['symbol']);
        self::assertSame(2, $this->parser->calls);
        $provider->configuration = null;
        $this->scan([$provider]);
        $this->scan([$provider]);
        self::assertSame(4, $this->parser->calls, 'A missing safe identity bypasses reuse.');
    }

    public function testUnknownCollectorAndParserBypassCaching(): void
    {
        $provider = new UnknownFactsProvider();
        $this->scan([$provider]);
        $this->scan([$provider]);
        self::assertSame(2, $this->parser->calls);
        self::assertSame([], glob($this->temporary . '/cache/*.json') ?: []);
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::exactly(2))->method('parse')->willReturn([]);
        $scanner = new SourceUsageScanner($parser, null, $this->cache);
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
    }

    public function testInjectedParserIdentityChangesOnTheSameScanner(): void
    {
        $scanner = new SourceUsageScanner($this->parser, null, $this->cache);
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
        $this->parser->identity = 'second-parser-version';
        $scanner->scan($this->project, ['src'], new EvidenceLedger());
        self::assertSame(2, $this->parser->calls);
    }

    public function testUnrelatedFrameworkDoesNotPreventSafeCoreFactReuse(): void
    {
        $framework = new class () implements FrameworkIntegration {
            public function name(): string
            {
                return 'without-source-collectors';
            }

            public function detect(ProjectState $project): FrameworkDetection
            {
                return new FrameworkDetection($this->name(), true);
            }

            public function rules(): iterable
            {
                return [];
            }

            public function defaultSourcePaths(ProjectState $project): array
            {
                return ['src'];
            }
        };
        self::assertSame($this->scan([$framework]), $this->scan([$framework]));
        self::assertSame(1, $this->parser->calls);
    }

    public function testFailingOptionalProviderIdentityBypassesReuseWithoutLosingFacts(): void
    {
        $provider = new CacheAwareFactsProvider();
        $provider->failIdentity = true;
        $first = $this->scan([$provider]);
        self::assertSame($first, $this->scan([$provider]));
        self::assertCount(2, $first['usages']);
        self::assertSame([], $first['uncertainties']);
        self::assertSame(2, $this->parser->calls);
    }

    public function testScannerIncludesAndCachesAnAutoloadModuleOutsideConventionalPaths(): void
    {
        $module = $this->project->path() . '/modules/Webkul/Shop';
        mkdir($module, 0700, true);
        file_put_contents($module . '/Controller.php', '<?php Acme\\Shop::run();');
        file_put_contents($this->project->path() . '/composer.json', '{"autoload":{"psr-4":{"Webkul\\\\Shop\\\\":"modules/Webkul/Shop"}}}');
        $project = (new ProjectStateBuilder())->build($this->project->path());
        $scanner = new SourceUsageScanner($this->parser, null, $this->cache);
        $uncertainties = [];
        $digests = [];
        foreach ([1, 2] as $run) {
            $usages = $scanner->scan($project, ['app'], new EvidenceLedger(), $uncertainties, false, [], $digests, true);
            self::assertCount(1, $usages);
            self::assertSame('modules/Webkul/Shop/Controller.php', $usages[0]->file());
        }
        self::assertSame([], $uncertainties);
        self::assertSame(1, $this->parser->calls);
    }

    public function testProviderFailureIsNotCachedOrHiddenOnLaterScans(): void
    {
        $provider = new CacheAwareFactsProvider();
        $provider->fail = true;
        $failed = $this->scan([$provider]);
        self::assertCount(1, $failed['uncertainties']);
        self::assertSame($failed, $this->scan([$provider]));
        self::assertSame(2, $this->parser->calls);
        self::assertSame([], glob($this->temporary . '/cache/*.json') ?: []);
    }

    public function testWarmFactsDoNotHideMutationsBetweenInputConsistencyCheckpoints(): void
    {
        $this->scan();
        $scanner = new SourceUsageScanner($this->parser, null, $this->cache);
        $tracker = new InputConsistencyTracker($this->project->path());
        $tracker->captureSource($scanner, $this->project, ['src']);
        $digests = [];
        $uncertainties = [];
        $scanner->scan($this->project, ['src'], new EvidenceLedger(), $uncertainties, true, [], $digests);
        file_put_contents($this->project->path() . '/src/Example.php', '<?php Acme\\Other::run();');
        file_put_contents($this->project->path() . '/src/Added.php', '<?php Acme\\Added::run();');
        $tracker->checkSource($scanner, $this->project, ['src'], $digests);
        self::assertStringContainsString('src/Example.php', $tracker->uncertainties()[0]);
        self::assertStringContainsString('src/Added.php', $tracker->uncertainties()[0]);
        self::assertSame(1, $this->parser->calls);
    }

    /**
     * @param list<FrameworkIntegration> $frameworks
     * @return array<string, mixed>
     */
    private function scan(array $frameworks = []): array
    {
        $ledger = new EvidenceLedger();
        $uncertainties = [];
        $usages = (new SourceUsageScanner($this->parser, null, $this->cache))->scan($this->project, ['src'], $ledger, $uncertainties, true, $frameworks);

        return [
            'usages' => array_map(static fn ($usage): array => $usage->toArray(), $usages),
            'evidence' => array_map(static fn ($evidence): array => $evidence->toArray(), $ledger->all()),
            'uncertainties' => $uncertainties,
        ];
    }

    /** @return array<string, string> */
    private function treeDigests(): array
    {
        $digests = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project->path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $digests[$file->getPathname()] = (string) hash_file('sha256', $file->getPathname());
        }

        return $digests;
    }
}

final class CountingFactsParser implements Parser, SourceUsageCacheKeyProvider
{
    public int $calls = 0;
    public string $identity = 'counting-parser-v1';
    private Parser $parser;

    public function __construct()
    {
        $factory = new ParserFactory();
        $reflection = new \ReflectionObject($factory);
        $this->parser = $reflection->hasMethod('createForNewestSupportedVersion')
            ? $reflection->getMethod('createForNewestSupportedVersion')->invoke($factory)
            : $reflection->getMethod('create')->invoke($factory, constant(ParserFactory::class . '::PREFER_PHP7'));
    }

    /** @return ?array<Node\Stmt> */
    public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array
    {
        ++$this->calls;

        return $this->parser->parse($code, $errorHandler);
    }

    /** @return array<mixed> */
    public function getTokens(): array
    {
        return [];
    }

    public function sourceUsageCacheKey(): string
    {
        return $this->identity;
    }
}

class UnknownFactsProvider implements FrameworkIntegration, SourceUsageVisitorProvider
{
    public ?string $configuration = 'original';
    public bool $fail = false;

    public function name(): string
    {
        return 'cache-fixture';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        return new FrameworkDetection($this->name(), true);
    }

    public function rules(): iterable
    {
        return [];
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src'];
    }

    public function sourceUsageVisitors(string $relativeFile): iterable
    {
        if ($this->fail) {
            throw new \RuntimeException('Fixture provider failed.');
        }
        yield new class ($this->configuration ?? 'unkeyed') extends NodeVisitorAbstract implements SourceUsageCollector {
            private string $symbol;

            public function __construct(string $symbol)
            {
                $this->symbol = $symbol;
            }

            public function usages(): array
            {
                return [['symbol' => $this->symbol, 'usage_type' => 'fixture', 'line' => 1]];
            }
        };
    }
}

final class CacheAwareFactsProvider extends UnknownFactsProvider implements SourceUsageCacheKeyProvider
{
    public bool $failIdentity = false;

    public function sourceUsageCacheKey(): ?string
    {
        if ($this->failIdentity) {
            throw new \RuntimeException('The provider cannot identify its source implementation.');
        }
        return $this->configuration;
    }
}
