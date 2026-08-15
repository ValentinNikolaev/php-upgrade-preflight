<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

final class FrameworkStageTarget
{
    private string $id;
    private string $framework;
    private int $fromMajor;
    private int $toMajor;
    private UpgradeTargetSet $targets;
    private string $analysisPhp;
    /** @var list<UpgradeTarget> */
    private array $remediationTargets;
    /** @var array<string, list<string>> */
    private array $remediationEvidence;
    /** @var list<string> */
    private array $evidence;

    /**
     * @param list<UpgradeTarget> $remediationTargets
     * @param array<string, list<string>> $remediationEvidence
     * @param list<string> $evidence
     */
    public function __construct(
        string $id,
        string $framework,
        int $fromMajor,
        int $toMajor,
        UpgradeTargetSet $targets,
        string $analysisPhp,
        array $remediationTargets,
        array $remediationEvidence,
        array $evidence
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $id) !== 1) {
            throw new \InvalidArgumentException('Framework stage IDs must be stable lowercase identifiers.');
        }
        if ($framework === '' || $fromMajor < 0 || $toMajor !== $fromMajor + 1) {
            throw new \InvalidArgumentException('Framework stages must describe one ascending adjacent hop.');
        }
        if ($targets->targetPhp() !== $analysisPhp) {
            throw new \InvalidArgumentException('A stage analysis PHP value must equal its exact Composer PHP target.');
        }
        if ($evidence === []) {
            throw new \InvalidArgumentException('A framework stage target must reference evidence.');
        }

        $normalizedRemediations = [];
        foreach ($remediationTargets as $target) {
            if (!$target instanceof UpgradeTarget) {
                throw new \InvalidArgumentException('Stage remediation targets must be UpgradeTarget instances.');
            }
            $package = strtolower($target->package());
            if (!isset($remediationEvidence[$package]) || $remediationEvidence[$package] === []) {
                throw new \InvalidArgumentException(sprintf('Stage remediation target "%s" must reference evidence.', $package));
            }
            $normalizedRemediations[$package] = new UpgradeTarget($package, $target->constraint());
        }
        ksort($normalizedRemediations, SORT_STRING);

        $this->id = $id;
        $this->framework = strtolower($framework);
        $this->fromMajor = $fromMajor;
        $this->toMajor = $toMajor;
        $this->targets = $targets;
        $this->analysisPhp = $analysisPhp;
        $this->remediationTargets = array_values($normalizedRemediations);
        $this->remediationEvidence = $remediationEvidence;
        $this->evidence = array_values(array_unique($evidence));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function framework(): string
    {
        return $this->framework;
    }

    public function fromMajor(): int
    {
        return $this->fromMajor;
    }

    public function toMajor(): int
    {
        return $this->toMajor;
    }

    public function targets(): UpgradeTargetSet
    {
        return $this->targets;
    }

    public function analysisPhp(): string
    {
        return $this->analysisPhp;
    }

    /** @return list<UpgradeTarget> */
    public function remediationTargets(): array
    {
        return array_map(
            static fn (UpgradeTarget $target): UpgradeTarget => new UpgradeTarget($target->package(), $target->constraint()),
            $this->remediationTargets
        );
    }

    /** @return list<string> */
    public function remediationEvidence(string $package): array
    {
        return $this->remediationEvidence[strtolower($package)] ?? [];
    }

    /** @return list<string> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'framework' => $this->framework,
            'from_major' => $this->fromMajor,
            'to_major' => $this->toMajor,
            'targets' => $this->targets->toArray(),
            'analysis_php' => $this->analysisPhp,
            'remediation_targets' => array_map(function (UpgradeTarget $target): array {
                return $target->toArray() + ['evidence' => $this->remediationEvidence($target->package())];
            }, $this->remediationTargets),
            'evidence' => $this->evidence,
        ];
    }
}
