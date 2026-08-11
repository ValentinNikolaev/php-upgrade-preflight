<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class DistributionPayloadVerifier
{
    /** @return list<string> */
    public function verify(string $expectedDirectory, string $actualDirectory): array
    {
        $expected = $this->inventory($expectedDirectory);
        $actual = $this->inventory($actualDirectory);
        $errors = [];

        foreach (array_diff_key($expected, $actual) as $path => $_record) {
            $errors[] = sprintf('Distribution payload is missing %s.', $path);
        }
        foreach (array_diff_key($actual, $expected) as $path => $_record) {
            $errors[] = sprintf('Distribution payload contains unexpected file %s.', $path);
        }
        foreach (array_intersect_key($expected, $actual) as $path => $record) {
            if ($record !== $actual[$path]) {
                $errors[] = sprintf('Distribution payload differs at %s.', $path);
            }
        }

        sort($errors, SORT_STRING);

        return $errors;
    }

    /** @return array<string, array{sha256:string, executable:bool}> */
    private function inventory(string $directory): array
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new \InvalidArgumentException(sprintf('Distribution directory does not exist: %s', $directory));
        }

        $inventory = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                throw new \RuntimeException(sprintf('Unable to hash distribution file: %s', $path));
            }

            $permissions = $file->getPerms();
            $inventory[$relative] = [
                'sha256' => $hash,
                'executable' => ($permissions & 0111) !== 0,
            ];
        }
        ksort($inventory, SORT_STRING);

        return $inventory;
    }
}
