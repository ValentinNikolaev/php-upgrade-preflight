<?php

namespace App\Http;

use App\Http\Middleware\TrustHosts;

final class Kernel
{
    protected array $middleware = [
        TrustHosts::class,
    ];
}
