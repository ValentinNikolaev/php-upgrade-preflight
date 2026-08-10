<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class PackageAdvisoryDefinition implements RuleDefinition
{
    public const REPLACE_IGNITION = 'replace_ignition';
    public const REMOVE_TRUSTED_PROXY = 'remove_trusted_proxy';
    public const REVIEW_CORS_REMOVAL = 'review_cors_removal';
    public const PUBLISH_MIGRATIONS = 'publish_migrations';
    public const REVIEW_DBAL_REMOVAL = 'review_dbal_removal';
    public const REPLACE_FLYSYSTEM_SFTP = 'replace_flysystem_sftp';
    public const REVIEW_LEGACY_HELPERS = 'review_legacy_helpers';

    private string $key;
    private string $package;
    private RuleApplicability $applicability;
    private string $action;
    private string $severity;
    /** @var list<string> */
    private array $sources;

    /** @param list<string> $sources */
    public function __construct(
        string $key,
        string $package,
        RuleApplicability $applicability,
        string $action,
        string $severity,
        array $sources
    ) {
        $this->key = $key;
        $this->package = strtolower($package);
        $this->applicability = $applicability;
        $this->action = $action;
        $this->severity = $severity;
        $this->sources = $sources;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function applicability(): RuleApplicability
    {
        return $this->applicability;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function expectedPackage(): ?string
    {
        switch ($this->action) {
            case self::REPLACE_IGNITION:
                return 'facade/ignition';
            case self::REMOVE_TRUSTED_PROXY:
                return 'fideloper/proxy';
            case self::REVIEW_CORS_REMOVAL:
                return 'fruitcake/laravel-cors';
            case self::REVIEW_DBAL_REMOVAL:
                return 'doctrine/dbal';
            case self::REPLACE_FLYSYSTEM_SFTP:
                return 'league/flysystem-sftp';
            case self::REVIEW_LEGACY_HELPERS:
                return 'laravel/helpers';
        }

        return null;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /** @return list<string> */
    public function sources(): array
    {
        return $this->sources;
    }
}
