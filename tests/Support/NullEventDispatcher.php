<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use Illuminate\Contracts\Events\Dispatcher;

final class NullEventDispatcher implements Dispatcher
{
    /**
     * @param string|array<string> $events
     * @param callable|string|null $listener
     */
    public function listen($events, $listener = null): void
    {
    }

    public function hasListeners($eventName): bool
    {
        return false;
    }

    public function subscribe($subscriber): void
    {
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>|null
     */
    public function until($event, $payload = []): ?array
    {
        return null;
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>|null
     */
    public function dispatch($event, $payload = [], $halt = false): ?array
    {
        return null;
    }

    /** @param array<mixed> $payload */
    public function push($event, $payload = []): void
    {
    }

    public function flush($event): void
    {
    }

    public function forget($event): void
    {
    }

    public function forgetPushed(): void
    {
    }
}
