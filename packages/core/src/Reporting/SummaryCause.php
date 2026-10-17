<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Reporting;

use PhpUpgradePreflight\Core\Model\Blocker;

/** A display group retains the recorded cause, without inferring a repair. */
final class SummaryCause
{
    private Blocker $blocker;
    private string $scope;
    private int $count = 0;
    /** @var array<string, true> */
    private array $references = [];

    public function __construct(Blocker $blocker, string $scope)
    {
        $this->blocker = $blocker;
        $this->scope = $scope;
    }

    public static function key(Blocker $blocker, string $scope): string
    {
        return serialize([$scope, $blocker->type(), $blocker->subject(), $blocker->blocker(),
            $blocker->lockedVersion(), $blocker->requestedConstraint(), $blocker->conflict()]);
    }

    /** @param list<string> $references */
    public function observe(array $references): void
    {
        ++$this->count;
        foreach ($references as $reference) {
            $this->references[$reference] = true;
        }
    }

    public function blocker(): Blocker
    {
        return $this->blocker;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function references(): string
    {
        $references = array_keys($this->references);
        sort($references, SORT_STRING);

        return implode(', ', array_slice($references, 0, 3))
            . (count($references) > 3 ? sprintf(' (+%d)', count($references) - 3) : '');
    }
}
