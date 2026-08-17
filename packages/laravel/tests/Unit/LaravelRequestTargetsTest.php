<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelRequestTargets;
use PHPUnit\Framework\TestCase;

final class LaravelRequestTargetsTest extends TestCase
{
    /** @dataProvider laravelFamilyProvider */
    public function testItRecognizesTheLaravelPackageFamily(string $package, bool $expected): void
    {
        self::assertSame($expected, LaravelRequestTargets::present($this->request([$package => '^9.0'])));
    }

    /** @return array<string, array{string, bool}> */
    public function laravelFamilyProvider(): array
    {
        return [
            'framework' => ['laravel/framework', true],
            'illuminate component' => ['illuminate/support', true],
            'first-party non-framework package' => ['laravel/passport', false],
            'unrelated package' => ['vendor/package', false],
        ];
    }

    public function testAnEmptyRequestHasNoLaravelTarget(): void
    {
        self::assertFalse(LaravelRequestTargets::present($this->request([])));
        self::assertSame([], LaravelRequestTargets::constraints($this->request([])));
    }

    /**
     * Both transition assessment and stage planning put these constraints into evidence context,
     * so the order has to be a property of the package name rather than of the request.
     */
    public function testConstraintsAreKeyedByPackageNameAndOrderedDeterministically(): void
    {
        $request = $this->request([
            'illuminate/support' => '^9.0',
            'vendor/package' => '^1.0',
            'illuminate/console' => '^9.0',
            'laravel/framework' => '^9.0',
        ]);

        self::assertSame([
            'illuminate/console' => '^9.0',
            'illuminate/support' => '^9.0',
            'laravel/framework' => '^9.0',
        ], LaravelRequestTargets::constraints($request));
    }

    /** @param array<string, string> $targets */
    private function request(array $targets): UpgradeRequest
    {
        $packageTargets = [];
        foreach ($targets as $package => $constraint) {
            $packageTargets[] = new UpgradeTarget($package, $constraint);
        }

        return new UpgradeRequest(__DIR__, $packageTargets, null, '8.1');
    }
}
