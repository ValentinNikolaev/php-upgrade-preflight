<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LaravelRemovedSourcePipelineTest extends TestCase
{
    /** @dataProvider sourceProvider */
    public function testActualParserPipelinePreservesSourceAndRequiresActiveResolvedIdentity(int $from, int $to, string $source, string $summaryPart, int $expected): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'preflight-laravel-source-pipeline-' . bin2hex(random_bytes(8));
        $sourcePath = $path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Example.php';
        mkdir(dirname($sourcePath), 0700, true);
        file_put_contents($sourcePath, $source);
        $before = hash_file('sha256', $sourcePath);

        try {
            $project = new ProjectState($path, new ComposerJson(['require' => ['laravel/framework' => '^' . $from . '.0'], 'autoload' => ['psr-4' => ['App\\' => 'app/']]]), new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => $from . '.0.0']]]));
            $request = new UpgradeRequest($path, [new UpgradeTarget('laravel/framework', '^' . $to . '.0')], null, '8.3');
            $integration = new LaravelFrameworkIntegration();
            $evidence = new EvidenceLedger();
            $uncertainties = [];
            $usages = (new SourceUsageScanner())->scan($project, ['app'], $evidence, $uncertainties, true, [$integration]);
            $engine = new FrameworkRuleEngine([$integration]);
            $guidance = $engine->assessTransitions([$integration], $project, $request, $evidence);
            $findings = $engine->evaluate([$integration], $project, $request, $evidence, $usages, $guidance, '2.8.0');
            $matched = array_values(array_filter($findings, static fn ($finding): bool => strpos($finding->summary(), $summaryPart) !== false));

            self::assertSame([], $uncertainties);
            self::assertCount($expected, $matched);
            self::assertSame($before, hash_file('sha256', $sourcePath));
            foreach ($matched as $finding) {
                $sources = array_values(array_filter($evidence->all(), static fn ($item): bool => $item->evidenceClass() === 'E3' && in_array($item->id(), $finding->evidence(), true)));
                self::assertNotEmpty($sources);
                foreach ($sources as $item) {
                    self::assertSame('app/Example.php', $item->context()['file']);
                    self::assertIsInt($item->context()['line']);
                }
            }
        } finally {
            (new Filesystem())->remove($path);
            self::assertDirectoryDoesNotExist($path);
        }
    }

    /** @return iterable<string, array{int, int, string, string, int}> */
    public function sourceProvider(): iterable
    {
        yield 'global helper' => [7, 8, '<?php elixir("app.css");', 'removed elixir helper', 1];
        yield 'explicit global helper within namespace' => [7, 8, '<?php namespace App; \\elixir("app.css");', 'removed elixir helper', 1];
        yield 'global function import alias' => [7, 8, '<?php namespace App; use function elixir as asset; asset("app.css");', 'removed elixir helper', 1];
        yield 'same-namespace helper declared locally' => [7, 8, '<?php namespace App; function elixir($asset) { return $asset; } elixir("app.css");', 'removed elixir helper', 0];
        yield 'ambiguous namespaced fallback' => [7, 8, '<?php namespace App; elixir("app.css");', 'removed elixir helper', 0];
        yield 'explicit unrelated function' => [7, 8, '<?php namespace App; \\App\\elixir("app.css");', 'removed elixir helper', 0];
        yield 'unrelated function import alias' => [7, 8, '<?php namespace App; use function Vendor\\elixir as asset; asset("app.css");', 'removed elixir helper', 0];
        yield 'unused function import' => [7, 8, '<?php namespace App; use function elixir as asset;', 'removed elixir helper', 0];
        yield 'removed closure import alias is actively instantiated' => [8, 9, '<?php namespace App; use Illuminate\\Queue\\SerializableClosure as OldClosure; new OldClosure($callback);', 'removed Illuminate\\Queue\\SerializableClosure', 1];
        yield 'unused removed closure import' => [8, 9, '<?php namespace App; use Illuminate\\Queue\\SerializableClosure as OldClosure;', 'removed Illuminate\\Queue\\SerializableClosure', 0];
        yield 'removed testing trait import alias' => [9, 10, '<?php namespace App; use Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices as OldMocks; class Example { use OldMocks; }', 'removed MocksApplicationServices', 1];
        yield 'unused removed testing trait import' => [9, 10, '<?php namespace App; use Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices as OldMocks;', 'removed MocksApplicationServices', 0];
        yield 'removed UUID trait import alias' => [11, 12, '<?php namespace App; use Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids as OldUuids; class Example { use OldUuids; }', 'removed HasVersion7Uuids', 1];
        yield 'unused UUID trait import' => [11, 12, '<?php namespace App; use Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids as OldUuids;', 'removed HasVersion7Uuids', 0];
        yield 'deprecated CSRF middleware inherited through alias' => [12, 13, '<?php namespace App; use Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken as LegacyCsrf; class Example extends LegacyCsrf {}', 'deprecated aliases remain available', 1];
        yield 'deprecated CSRF middleware exclusion alias' => [12, 13, '<?php namespace App; use Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken as LegacyCsrf; $route->withoutMiddleware(LegacyCsrf::class);', 'deprecated aliases remain available', 1];
        yield 'unused deprecated CSRF import' => [12, 13, '<?php namespace App; use Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken as LegacyCsrf;', 'deprecated aliases remain available', 0];
    }
}
