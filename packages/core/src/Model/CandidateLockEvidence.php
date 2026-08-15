<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

final class CandidateLockEvidence
{
    private string $sha256;
    private ?string $contentHash;
    private int $packageCount;

    public function __construct(string $sha256, ?string $contentHash, int $packageCount)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new \InvalidArgumentException('Candidate lock SHA-256 must be a lowercase hexadecimal digest.');
        }

        if ($packageCount < 0) {
            throw new \InvalidArgumentException('Candidate lock package count cannot be negative.');
        }

        $this->sha256 = $sha256;
        $this->contentHash = $contentHash;
        $this->packageCount = $packageCount;
    }

    /** Fingerprint LF-normalized lockfile bytes so evidence is stable across operating systems. */
    public static function fromFile(string $path, ComposerLock $lock): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Unable to fingerprint the candidate Composer lockfile.');
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Unable to fingerprint the candidate Composer lockfile.');
        }

        $normalizedContents = str_replace(["\r\n", "\r"], "\n", $contents);

        return self::fromLock($lock, hash('sha256', $normalizedContents));
    }

    public static function fromLock(ComposerLock $lock, ?string $sha256 = null): self
    {
        $data = $lock->data();
        if ($sha256 === null) {
            $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $sha256 = hash('sha256', $encoded);
        }

        $contentHash = isset($data['content-hash']) && is_string($data['content-hash'])
            ? $data['content-hash']
            : null;

        return new self($sha256, $contentHash, count($lock->packages()));
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function contentHash(): ?string
    {
        return $this->contentHash;
    }

    public function packageCount(): int
    {
        return $this->packageCount;
    }

    /** @return array{sha256: string, content_hash: ?string, package_count: int} */
    public function toArray(): array
    {
        return [
            'sha256' => $this->sha256,
            'content_hash' => $this->contentHash,
            'package_count' => $this->packageCount,
        ];
    }
}
