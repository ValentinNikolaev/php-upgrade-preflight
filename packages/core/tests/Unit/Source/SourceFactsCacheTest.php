<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SourceFactsCacheTest extends TestCase
{
    private string $temporary;
    private string $project;
    private string $directory;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/source-facts-cache-test-' . bin2hex(random_bytes(8));
        $this->project = $this->temporary . '/project';
        $this->directory = $this->temporary . '/cache';
        mkdir($this->project, 0700, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    public function testFactsRoundTripWithPrivatePermissionsAndNoPartialFile(): void
    {
        $facts = [['symbol' => 'Acme\\Client', 'usage_type' => 'static_call', 'line' => 2]];
        $cache = new SourceFactsCache($this->directory);
        $cache->put($this->project, 'key', $facts);

        self::assertSame($facts, (new SourceFactsCache($this->directory))->get($this->project, 'key'));
        self::assertFileDoesNotExist($this->directory . '/pending.json');
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0700, fileperms($this->directory) & 0777);
            self::assertSame(0600, fileperms($this->entryPath('key')) & 0777);
        }
    }

    public function testMalformedAndCorruptEntriesAreMisses(): void
    {
        $cache = new SourceFactsCache($this->directory);
        $cache->put($this->project, 'key', []);
        foreach (['not json', '{"key":"key"}', str_repeat('x', 8193)] as $contents) {
            file_put_contents($this->entryPath('key'), $contents);
            self::assertNull($cache->get($this->project, 'key'));
        }
        foreach ([['symbol' => 'Acme', 'usage_type' => 'static_call', 'line' => '2'], ['unexpected' => 'shape']] as $fact) {
            $encoded = (string) json_encode([$fact]);
            file_put_contents($this->entryPath('key'), (string) json_encode(['key' => 'key', 'facts' => $encoded, 'sha256' => hash('sha256', $encoded)]));
            self::assertNull($cache->get($this->project, 'key'));
        }
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        $cache->put($this->project, 'key', []);
        $entry = json_decode((string) file_get_contents($this->entryPath('key')), true);
        $entry['sha256'] = str_repeat('0', 64);
        file_put_contents($this->entryPath('key'), (string) json_encode($entry));
        self::assertNull($cache->get($this->project, 'key'));
    }

    public function testUnavailableDisabledOversizedAndTargetOwnedCachesDoNotWrite(): void
    {
        file_put_contents($this->directory, 'unavailable');
        $unavailable = new SourceFactsCache($this->directory);
        $unavailable->put($this->project, 'key', []);
        self::assertNull($unavailable->get($this->project, 'key'));
        self::assertSame('unavailable', file_get_contents($this->directory));
        SourceFactsCache::disabled()->put($this->project, 'key', []);

        $inside = new SourceFactsCache($this->project . '/cache');
        $inside->put($this->project, 'key', []);
        self::assertNull($inside->get($this->project, 'key'));
        self::assertDirectoryDoesNotExist($this->project . '/cache');
        unlink($this->directory);
        $oversized = new SourceFactsCache($this->directory);
        $oversized->put($this->project, 'key', [['symbol' => str_repeat('x', 9000), 'usage_type' => 'static_call', 'line' => 1]]);
        self::assertNull($oversized->get($this->project, 'key'));
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testSymlinkedParentCannotRedirectWritesIntoTarget(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Requires unprivileged Unix symlinks.');
        }
        symlink($this->project, $this->temporary . '/redirect');
        (new SourceFactsCache($this->temporary . '/redirect/cache'))->put($this->project, 'key', []);
        self::assertDirectoryDoesNotExist($this->project . '/cache');
    }

    public function testSharedOrSymlinkedEntriesAreNeverTrusted(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Unix permission checks.');
        }
        $cache = new SourceFactsCache($this->directory);
        $cache->put($this->project, 'key', []);
        chmod($this->entryPath('key'), 0644);
        self::assertNull($cache->get($this->project, 'key'));
        chmod($this->entryPath('key'), 0600);
        rename($this->entryPath('key'), $this->temporary . '/entry');
        symlink($this->temporary . '/entry', $this->entryPath('key'));
        self::assertNull($cache->get($this->project, 'key'));
        chmod($this->directory, 0755);
        self::assertNull($cache->get($this->project, 'key'));
    }

    public function testHardlinkedPendingFilesCannotMutateTargetBytesOrPermissions(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Unix hardlink and permission checks.');
        }
        mkdir($this->directory, 0700);
        $input = $this->project . '/Source.php';
        file_put_contents($input, '<?php Acme\\Client::run();');
        chmod($input, 0600);
        link($input, $this->directory . '/pending.json');
        $cache = new SourceFactsCache($this->directory);
        $cache->put($this->project, 'key', []);
        self::assertSame('<?php Acme\\Client::run();', file_get_contents($input));
        self::assertSame(0600, fileperms($input) & 0777);
        unlink($this->directory . '/pending.json');
        unlink($this->directory . '/writer.lock');
        link($input, $this->directory . '/writer.lock');
        $cache->put($this->project, 'key', []);
        self::assertSame('<?php Acme\\Client::run();', file_get_contents($input));
        self::assertNull($cache->get($this->project, 'key'));
    }

    public function testPrimarySlotCollisionsUseABoundedAlternative(): void
    {
        $keys = [];
        for ($index = 0; ; ++$index) {
            $key = (string) $index;
            $slot = substr(hash('sha256', $key), 0, 4);
            if (isset($keys[$slot])) {
                $previous = $keys[$slot];
                break;
            }
            $keys[$slot] = $key;
        }
        $cache = new SourceFactsCache($this->directory);
        $cache->put($this->project, $previous, []);
        $cache->put($this->project, $key, []);
        self::assertSame([], $cache->get($this->project, $previous));
        self::assertSame([], $cache->get($this->project, $key));
        self::assertCount(2, glob($this->directory . '/*.json') ?: []);
    }

    private function entryPath(string $key): string
    {
        return $this->directory . '/' . substr(hash('sha256', $key), 0, 4) . '.json';
    }
}
