<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

use PhpUpgradePreflight\Core\Support\PathExposurePolicy;

final class ProjectStateFingerprint
{
    private string $manifestSha256;
    private string $lockSha256;
    private string $platformSha256;
    private string $executionPolicySha256;
    private string $stateSha256;

    private function __construct(
        string $manifestSha256,
        string $lockSha256,
        string $platformSha256,
        string $executionPolicySha256,
        string $stateSha256
    ) {
        $this->manifestSha256 = $manifestSha256;
        $this->lockSha256 = $lockSha256;
        $this->platformSha256 = $platformSha256;
        $this->executionPolicySha256 = $executionPolicySha256;
        $this->stateSha256 = $stateSha256;
    }

    /** @param array<string, mixed> $executionPolicy */
    public static function fromState(
        ProjectState $state,
        TargetPlatform $platform,
        string $analysisPhp,
        array $executionPolicy
    ): self {
        $repositoryPaths = PathExposurePolicy::localRepositoryPaths(
            $state->composerJson()->data(),
            $state->path()
        );
        $sanitized = PathExposurePolicy::sanitizeCanonicalReport([
            'manifest' => $state->composerJson()->data(),
            'lock' => $state->composerLock()->data(),
        ], $state->path(), null, $repositoryPaths);
        $manifest = self::digest($sanitized['manifest']);
        $lock = self::digest($sanitized['lock']);
        $effectivePlatform = self::digest([
            'php' => $analysisPhp,
            'composer_overrides' => $platform->composerPlatformOverrides(),
        ]);
        $policy = self::digest($executionPolicy);

        return new self(
            $manifest,
            $lock,
            $effectivePlatform,
            $policy,
            self::digest([
                'manifest' => $manifest,
                'lock' => $lock,
                'platform' => $effectivePlatform,
                'execution_policy' => $policy,
            ])
        );
    }

    public function stateSha256(): string
    {
        return $this->stateSha256;
    }

    /** @return array{manifest_sha256: string, lock_sha256: string, platform_sha256: string, execution_policy_sha256: string, state_sha256: string} */
    public function toArray(): array
    {
        return [
            'manifest_sha256' => $this->manifestSha256,
            'lock_sha256' => $this->lockSha256,
            'platform_sha256' => $this->platformSha256,
            'execution_policy_sha256' => $this->executionPolicySha256,
            'state_sha256' => $this->stateSha256,
        ];
    }

    /** @param mixed $value */
    private static function digest($value): string
    {
        $encoded = json_encode(self::canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!self::isList($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    /** @param array<mixed> $value */
    private static function isList(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            ++$expected;
        }

        return true;
    }
}
