<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry;
use PHPUnit\Framework\TestCase;

final class FrameworkIntegrationRegistryTest extends TestCase
{
    public function testItDiscoversInstalledFrameworkIntegrations(): void
    {
        $integrations = (new FrameworkIntegrationRegistry())->installed();

        self::assertSame(['laravel'], array_map(static fn ($integration): string => $integration->name(), $integrations));
    }

    public function testItIgnoresUnavailableOptionalIntegrations(): void
    {
        self::assertSame([], (new FrameworkIntegrationRegistry(['Missing\\Framework\\Integration']))->installed());
    }
}
