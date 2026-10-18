<?php

declare(strict_types=1);

require __DIR__ . '/runtime.php';
r4Autoload();

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

function r4Live(int $pid): bool
{
    $stat = @file_get_contents('/proc/' . $pid . '/stat');
    return $stat !== false && preg_match('/^\d+ \(.*\) ([^Z]) /', $stat) === 1;
}

/** @return list<int> */
function r4Children(int $pid): array
{
    $children = [];
    foreach (glob('/proc/[0-9]*/stat') ?: [] as $file) {
        $stat = @file_get_contents($file);
        if ($stat !== false && preg_match('/^(\d+) \(.*\) \S (\d+) /', $stat, $match) === 1 && (int) $match[2] === $pid) {
            $children[] = (int) $match[1];
            array_push($children, ...r4Children((int) $match[1]));
        }
    }
    return $children;
}

$wrapper = $argv[1];
$legacy = ($argv[2] ?? '') === 'legacy';
$results = [];
foreach (['timeout', 'stop-zero', 'stop-normal'] as $mode) {
    $process = new Process([$wrapper, 'update'], '/scratch', ['R4_PROBE' => 'timeout'], null, 0.5);
    $process->start();
    $pid = $process->getPid();
    if ($pid === null) {
        throw new RuntimeException('probe did not start');
    }
    usleep(250000);
    $children = r4Children($pid);
    $command = @file_get_contents('/proc/' . $pid . '/cmdline');
    if ($mode === 'timeout') {
        try {
            $process->wait();
            throw new RuntimeException('Symfony timeout did not fire: ' . $process->getErrorOutput() . $process->getOutput());
        } catch (ProcessTimedOutException $expected) {
        }
    } else {
        $process->stop($mode === 'stop-zero' ? 0 : 1);
    }
    usleep(100000);
    $survivors = array_values(array_filter([$pid, ...$children], 'r4Live'));
    $results[$mode] = ['pid' => $pid, 'children' => $children, 'survivors' => $survivors];
    // Legacy reproduction is isolated; terminate the orphan before the next control.
    foreach ($survivors as $survivor) {
        posix_kill($survivor, 9);
    }
    if ($legacy ? $survivors === [] : $survivors !== [] || $children !== [] || $command === false || !str_starts_with($command, "php\0")) {
        throw new RuntimeException('unexpected cancellation ownership');
    }
    if (!$legacy) {
        if (is_file('/output/composer/' . $pid . '.metrics.json')) {
            throw new RuntimeException('interrupted shutdown metrics invented');
        }
        $next = new Process([$wrapper, '--version'], '/scratch');
        if ($next->run() !== 0 || r4Live($pid) || array_filter($children, 'r4Live') !== []) {
            throw new RuntimeException('cancellation leaked into next scenario');
        }
    }
}
echo json_encode($results, JSON_THROW_ON_ERROR) . "\n";
