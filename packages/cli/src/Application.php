<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

final class Application implements CommandRunner
{
    private CommandRunner $analyzeCommand;
    private CommandRunner $wizardCommand;

    public function __construct(?CommandRunner $analyzeCommand = null, ?CommandRunner $wizardCommand = null)
    {
        $this->analyzeCommand = $analyzeCommand ?? new AnalyzeCommand();
        $this->wizardCommand = $wizardCommand ?? new WizardCommand($this->analyzeCommand);
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        return ($argv[1] ?? null) === 'wizard'
            ? $this->wizardCommand->run($argv)
            : $this->analyzeCommand->run($argv);
    }
}
