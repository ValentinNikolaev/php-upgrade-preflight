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

    /**
     * The advisory action vocabulary, and the single source of truth for every
     * consumer: the catalog validator allow-list, the expected package binding,
     * the contradictory-advice exemption, and the rendered finding summary.
     *
     * package:   the package the action is bound to, or null when the action is
     *            deliberately package-agnostic and may be reused across packages.
     * exclusive: whether the action claims the only advice for its package on a
     *            transition. Package-agnostic deployment steps coexist with the
     *            version guidance a package rule already emits.
     * summary:   finding summary template receiving the advisory package as
     *            argument 1 and the target Laravel major as argument 2.
     *
     * An action that is absent here is rejected by the catalog validator and
     * refuses to render a summary, so a new action cannot resolve silently.
     */
    private const ACTIONS = [
        self::REPLACE_IGNITION => [
            'package' => 'facade/ignition',
            'exclusive' => true,
            'summary' => 'Replace facade/ignition with spatie/laravel-ignition for the Laravel %2$d target.',
        ],
        self::REMOVE_TRUSTED_PROXY => [
            'package' => 'fideloper/proxy',
            'exclusive' => true,
            'summary' => 'Remove fideloper/proxy and review the trusted proxy middleware for the Laravel %2$d target.',
        ],
        self::REVIEW_CORS_REMOVAL => [
            'package' => 'fruitcake/laravel-cors',
            'exclusive' => true,
            'summary' => 'Review removal of fruitcake/laravel-cors because Laravel %2$d integrates CORS middleware through the framework.',
        ],
        self::PUBLISH_MIGRATIONS => [
            'package' => null,
            'exclusive' => false,
            'summary' => 'Publish the %1$s migrations before deploying the Laravel %2$d upgrade; this package no longer loads its migrations automatically.',
        ],
        self::REVIEW_DBAL_REMOVAL => [
            'package' => 'doctrine/dbal',
            'exclusive' => true,
            'summary' => 'Review and remove doctrine/dbal if it was only installed for Laravel schema operations; Laravel %2$d no longer depends on it.',
        ],
        self::REPLACE_FLYSYSTEM_SFTP => [
            'package' => 'league/flysystem-sftp',
            'exclusive' => true,
            'summary' => 'Replace league/flysystem-sftp with league/flysystem-sftp-v3:^3.0 for the Laravel %2$d target.',
        ],
        self::REVIEW_LEGACY_HELPERS => [
            'package' => 'laravel/helpers',
            'exclusive' => true,
            'summary' => 'Review laravel/helpers and custom global array helpers before targeting Laravel %2$d; prefer Illuminate\\Support\\Arr replacements to avoid documented polyfill conflicts.',
        ],
    ];

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

    /** @return list<string> */
    public static function actions(): array
    {
        return array_keys(self::ACTIONS);
    }

    public static function isSupportedAction(string $action): bool
    {
        return isset(self::ACTIONS[$action]);
    }

    public function expectedPackage(): ?string
    {
        $action = self::ACTIONS[$this->action] ?? null;

        return $action === null ? null : $action['package'];
    }

    public function isExclusivePackageAdvice(): bool
    {
        $action = self::ACTIONS[$this->action] ?? null;

        return $action === null ? true : $action['exclusive'];
    }

    public function summary(int $targetMajor): string
    {
        $action = self::ACTIONS[$this->action] ?? null;
        if ($action === null) {
            throw new \LogicException(sprintf('Unsupported Laravel package advisory action: %s.', $this->action));
        }

        return sprintf($action['summary'], $this->package, $targetMajor);
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
