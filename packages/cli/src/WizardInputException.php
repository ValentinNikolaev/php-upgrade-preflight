<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

final class WizardInputException extends \RuntimeException
{
    private bool $cancelled;

    private function __construct(string $message, bool $cancelled)
    {
        parent::__construct($message);
        $this->cancelled = $cancelled;
    }

    public static function endOfInput(): self
    {
        return new self('Interactive input ended before the plan was confirmed. No analysis was run.', false);
    }

    public static function cancelled(): self
    {
        return new self('Wizard cancelled. No analysis was run.', true);
    }

    public function isCancellation(): bool
    {
        return $this->cancelled;
    }
}
