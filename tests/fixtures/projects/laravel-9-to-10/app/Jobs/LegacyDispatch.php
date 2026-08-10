<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Bus as QueueBus;

final class LegacyDispatch
{
    public function run(object $job): void
    {
        Bus::dispatchNow($job);
        QueueBus::dispatchNow($job);
        dispatch_now($job);
    }
}
