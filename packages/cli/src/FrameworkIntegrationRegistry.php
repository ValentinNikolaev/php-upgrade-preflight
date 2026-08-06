<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;

final class FrameworkIntegrationRegistry
{
    /** @var list<string> */
    private array $integrationClasses;

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

        return $integrations;
    }
}
