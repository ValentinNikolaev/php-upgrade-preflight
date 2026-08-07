<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;

final class FrameworkIntegrationRegistry
{
    /** @var list<string> */
    private array $integrationClasses;
    /** @var list<FrameworkIntegration>|null */
    private ?array $installed = null;

    /** @param list<string>|null $integrationClasses */
    public function __construct(?array $integrationClasses = null)
    {
        $this->integrationClasses = $integrationClasses ?? [
            'PhpUpgradePreflight\\Laravel\\LaravelFrameworkIntegration',
        ];
    }

    /** @return list<FrameworkIntegration> */
    public function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $integrations = [];

        foreach ($this->integrationClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $integration = new $class();
            if (!$integration instanceof FrameworkIntegration) {
                throw new \LogicException(sprintf('Framework integration "%s" must implement %s.', $class, FrameworkIntegration::class));
            }

            $integrations[] = $integration;
        }

        usort($integrations, static fn (FrameworkIntegration $left, FrameworkIntegration $right): int => strcmp(
            strtolower($left->name()),
            strtolower($right->name())
        ));

        return $this->installed = $integrations;
    }

    /** @param list<string> $requested */
    public function assertAvailable(array $requested): void
    {
        if ($requested === []) {
            return;
        }

        $available = array_map(
            static fn (FrameworkIntegration $integration): string => strtolower($integration->name()),
            $this->installed()
        );
        $unavailable = array_values(array_diff(array_map('strtolower', $requested), $available));

        if ($unavailable !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Requested framework integration%s unavailable: %s. Install the matching adapter package or remove the --framework option.',
                count($unavailable) === 1 ? ' is' : 's are',
                implode(', ', $unavailable)
            ));
        }
    }
}
