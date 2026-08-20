<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

final class PackageTargetValidation
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const NO_MATCHING_VERSION = 'no_matching_version';
    public const UNVERIFIED = 'unverified';
    public const INVALID = 'invalid';

    private string $status;
    private string $message;
    /** @var list<string> */
    private array $candidateConstraints;

    /** @param list<string> $candidateConstraints */
    public function __construct(string $status, string $message, array $candidateConstraints = [])
    {
        if (!in_array($status, [
            self::FOUND,
            self::NOT_FOUND,
            self::NO_MATCHING_VERSION,
            self::UNVERIFIED,
            self::INVALID,
        ], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown package validation status "%s".', $status));
        }

        $this->status = $status;
        $this->message = trim($message);
        $this->candidateConstraints = array_values(array_unique($candidateConstraints));
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return list<string> */
    public function candidateConstraints(): array
    {
        return $this->candidateConstraints;
    }

    public function permitsAnalysis(): bool
    {
        return in_array($this->status, [self::FOUND, self::UNVERIFIED], true);
    }
}
