<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

final class StageBlockerEntry
{
    public const DETECTED = 'detected';
    public const PERSISTS = 'persists';
    public const RESOLVED = 'resolved';
    public const SUPERSEDED = 'superseded';

    private string $id;
    private string $identityKey;
    private string $supersessionKey;
    private string $stageId;
    private int $attempt;
    private string $scenario;
    private string $category;
    private string $subject;
    private ?string $blockingPackage;
    private ?string $requestedConstraint;
    private ?string $constraint;
    /** @var list<string> */
    private array $dependencyPath;
    private string $confidence;
    private string $summary;
    /** @var list<string> */
    private array $options;
    private bool $blocking;
    /** @var list<string> */
    private array $evidence;
    /** @var array{stage_id: string, attempt: int, scenario: string} */
    private array $firstSeen;
    /** @var array{stage_id: string, attempt: int, scenario: string} */
    private array $lastSeen;
    private string $lifecycle;
    /** @var list<array{status: string, attempt: int, scenario: string, evidence: list<string>}> */
    private array $lifecycleHistory;
    /** @var list<array{attempt: int, scenario: string, evidence: list<string>}> */
    private array $observations;

    /** @param list<string> $transitionEvidence */
    public static function detected(
        string $stageId,
        int $attempt,
        string $scenario,
        Blocker $blocker,
        array $transitionEvidence = []
    ): self {
        $identity = self::identityFor($stageId, $blocker);
        $seen = ['stage_id' => $stageId, 'attempt' => $attempt, 'scenario' => $scenario];
        $evidence = array_values(array_unique(array_merge($blocker->evidence(), $transitionEvidence)));

        return new self(
            'stage-blocker-' . substr($identity, 0, 20),
            $identity,
            self::supersessionFor($stageId, $blocker),
            $stageId,
            $attempt,
            $scenario,
            $blocker->type(),
            $blocker->subject(),
            $blocker->blocker(),
            $blocker->requestedConstraint(),
            $blocker->conflict(),
            $blocker->dependencyPath(),
            $blocker->confidence(),
            $blocker->summary(),
            $blocker->options(),
            $blocker->blocksResolution(),
            $evidence,
            $seen,
            $seen,
            self::DETECTED,
            [['status' => self::DETECTED, 'attempt' => $attempt, 'scenario' => $scenario, 'evidence' => $evidence]],
            [['attempt' => $attempt, 'scenario' => $scenario, 'evidence' => $blocker->evidence()]]
        );
    }

    /**
     * @param list<string> $dependencyPath
     * @param list<string> $options
     * @param list<string> $evidence
     * @param array{stage_id: string, attempt: int, scenario: string} $firstSeen
     * @param array{stage_id: string, attempt: int, scenario: string} $lastSeen
     * @param list<array{status: string, attempt: int, scenario: string, evidence: list<string>}> $lifecycleHistory
     * @param list<array{attempt: int, scenario: string, evidence: list<string>}> $observations
     */
    private function __construct(
        string $id,
        string $identityKey,
        string $supersessionKey,
        string $stageId,
        int $attempt,
        string $scenario,
        string $category,
        string $subject,
        ?string $blockingPackage,
        ?string $requestedConstraint,
        ?string $constraint,
        array $dependencyPath,
        string $confidence,
        string $summary,
        array $options,
        bool $blocking,
        array $evidence,
        array $firstSeen,
        array $lastSeen,
        string $lifecycle,
        array $lifecycleHistory,
        array $observations
    ) {
        $this->id = $id;
        $this->identityKey = $identityKey;
        $this->supersessionKey = $supersessionKey;
        $this->stageId = $stageId;
        $this->attempt = $attempt;
        $this->scenario = $scenario;
        $this->category = $category;
        $this->subject = $subject;
        $this->blockingPackage = $blockingPackage;
        $this->requestedConstraint = $requestedConstraint;
        $this->constraint = $constraint;
        $this->dependencyPath = array_values($dependencyPath);
        $this->confidence = $confidence;
        $this->summary = $summary;
        $this->options = array_values($options);
        $this->blocking = $blocking;
        $this->evidence = array_values(array_unique($evidence));
        $this->firstSeen = $firstSeen;
        $this->lastSeen = $lastSeen;
        $this->lifecycle = $lifecycle;
        $this->lifecycleHistory = array_values($lifecycleHistory);
        $this->observations = array_values($observations);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function stageId(): string
    {
        return $this->stageId;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    /** @return list<string> */
    public function options(): array
    {
        return $this->options;
    }

    public function identityKey(): string
    {
        return $this->identityKey;
    }

    public function supersessionKey(): string
    {
        return $this->supersessionKey;
    }

    public function lifecycle(): string
    {
        return $this->lifecycle;
    }

    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    public function isActive(): bool
    {
        return !in_array($this->lifecycle, [self::RESOLVED, self::SUPERSEDED], true);
    }

    /** @return list<string> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function withObservation(int $attempt, string $scenario, Blocker $blocker): self
    {
        $lastSeen = ['stage_id' => $this->stageId, 'attempt' => $attempt, 'scenario' => $scenario];
        $history = $this->lifecycleHistory;
        $history[] = [
            'status' => self::PERSISTS,
            'attempt' => $attempt,
            'scenario' => $scenario,
            'evidence' => $blocker->evidence(),
        ];
        $observations = $this->observations;
        $observations[] = ['attempt' => $attempt, 'scenario' => $scenario, 'evidence' => $blocker->evidence()];

        return $this->copy(
            array_values(array_unique(array_merge($this->evidence, $blocker->evidence()))),
            $lastSeen,
            self::PERSISTS,
            $history,
            $observations
        );
    }

    public function withReappearance(int $attempt, string $scenario, Blocker $blocker): self
    {
        $lastSeen = ['stage_id' => $this->stageId, 'attempt' => $attempt, 'scenario' => $scenario];
        $history = $this->lifecycleHistory;
        $history[] = [
            'status' => self::DETECTED,
            'attempt' => $attempt,
            'scenario' => $scenario,
            'evidence' => $blocker->evidence(),
        ];
        $observations = $this->observations;
        $observations[] = ['attempt' => $attempt, 'scenario' => $scenario, 'evidence' => $blocker->evidence()];

        return $this->copy(
            array_values(array_unique(array_merge($this->evidence, $blocker->evidence()))),
            $lastSeen,
            self::DETECTED,
            $history,
            $observations
        );
    }

    /** @param list<string> $evidence */
    public function withLifecycle(string $lifecycle, int $attempt, string $scenario, array $evidence): self
    {
        if (!in_array($lifecycle, [self::RESOLVED, self::SUPERSEDED], true)) {
            throw new \InvalidArgumentException('Only terminal blocker lifecycle transitions may be applied directly.');
        }
        $lastSeen = ['stage_id' => $this->stageId, 'attempt' => $attempt, 'scenario' => $scenario];
        $history = $this->lifecycleHistory;
        $history[] = [
            'status' => $lifecycle,
            'attempt' => $attempt,
            'scenario' => $scenario,
            'evidence' => array_values(array_unique($evidence)),
        ];

        return $this->copy(
            array_values(array_unique(array_merge($this->evidence, $evidence))),
            $lastSeen,
            $lifecycle,
            $history,
            $this->observations
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stage_id' => $this->stageId,
            'attempt' => $this->attempt,
            'scenario' => $this->scenario,
            'category' => $this->category,
            'subject' => $this->subject,
            'blocking_package' => $this->blockingPackage,
            'requested_constraint' => $this->requestedConstraint,
            'constraint' => $this->constraint,
            'dependency_path' => $this->dependencyPath,
            'confidence' => $this->confidence,
            'summary' => $this->summary,
            'options' => $this->options,
            'blocking' => $this->blocking,
            'evidence' => $this->evidence,
            'first_seen' => $this->firstSeen,
            'last_seen' => $this->lastSeen,
            'lifecycle' => $this->lifecycle,
            'lifecycle_history' => $this->lifecycleHistory,
            'observations' => $this->observations,
        ];
    }

    /**
     * @param list<string> $evidence
     * @param array{stage_id: string, attempt: int, scenario: string} $lastSeen
     * @param list<array{status: string, attempt: int, scenario: string, evidence: list<string>}> $history
     * @param list<array{attempt: int, scenario: string, evidence: list<string>}> $observations
     */
    private function copy(
        array $evidence,
        array $lastSeen,
        string $lifecycle,
        array $history,
        array $observations
    ): self {
        return new self(
            $this->id,
            $this->identityKey,
            $this->supersessionKey,
            $this->stageId,
            $this->attempt,
            $this->scenario,
            $this->category,
            $this->subject,
            $this->blockingPackage,
            $this->requestedConstraint,
            $this->constraint,
            $this->dependencyPath,
            $this->confidence,
            $this->summary,
            $this->options,
            $this->blocking,
            $evidence,
            $this->firstSeen,
            $lastSeen,
            $lifecycle,
            $history,
            $observations
        );
    }

    private static function identityFor(string $stageId, Blocker $blocker): string
    {
        return hash('sha256', serialize([
            $stageId,
            $blocker->type(),
            $blocker->subject(),
            $blocker->requestedConstraint(),
            $blocker->blocker(),
            $blocker->conflict(),
            $blocker->dependencyPath(),
        ]));
    }

    private static function supersessionFor(string $stageId, Blocker $blocker): string
    {
        return hash('sha256', serialize([
            $stageId,
            $blocker->type(),
            $blocker->subject(),
            $blocker->blocker(),
            $blocker->dependencyPath(),
        ]));
    }
}
