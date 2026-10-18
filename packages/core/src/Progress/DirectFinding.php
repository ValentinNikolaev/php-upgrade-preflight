<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Progress;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\BlockerType;
use PhpUpgradePreflight\Core\Model\Confidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Support\OutputExcerpt;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;

/** An observed cause from a direct attempt, never an overall upgrade verdict. */
final class DirectFinding
{
    /** @param list<string> $lines */
    private function __construct(private array $lines)
    {
    }

    /** @param list<Blocker> $blockers */
    public static function first(
        array $blockers,
        EvidenceLedger $evidence,
        ProjectState $project,
        UpgradeRequest $request
    ): ?self {
        foreach ($blockers as $blocker) {
            if (!BlockerType::isSupported($blocker->type())
                || !$blocker->blocksResolution()
                || $blocker->type() === BlockerType::UNKNOWN_COMPOSER_FAILURE
                || $blocker->confidence() !== Confidence::HIGH
                || $blocker->evidence() === []
                || $blocker->options() === []
            ) {
                continue;
            }
            foreach ($blocker->evidence() as $reference) {
                if (!$evidence->has($reference)) {
                    continue 2;
                }
            }

            $package = $blocker->blocker() ?? $blocker->subject();
            $version = $blocker->lockedVersion() === null ? '' : ' ' . $blocker->lockedVersion();
            $safe = PathExposurePolicy::sanitizeCanonicalReport(
                ['lines' => [
                    '[finding] A direct Composer attempt reported: ' . $package . $version . '. ' . $blocker->summary(),
                    '[next] ' . $blocker->options()[0] . ' Repair not verified.',
                    '[evidence] ' . implode(', ', array_slice($blocker->evidence(), 0, 3)),
                    '[pending] Preliminary finding; remaining checks and the final result are still pending.',
                ]],
                $request->projectPath(),
                $request->outputPath(),
                PathExposurePolicy::localRepositoryPaths($project->composerJson()->data(), $project->path())
            );
            /** @var list<string> $lines */
            $lines = $safe['lines'];

            return new self(array_map(static function (string $line): string {
                /** @var string $line */
                $line = json_decode(json_encode($line, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                $line = preg_replace('/\p{Cc}/u', ' ', $line) ?? '';

                return str_replace(PHP_EOL, ' ', OutputExcerpt::bounded($line, 320));
            }, $lines));
        }

        return null;
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }
}
