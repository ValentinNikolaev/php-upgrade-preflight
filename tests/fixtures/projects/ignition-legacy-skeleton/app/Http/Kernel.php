<?php

declare(strict_types=1);

namespace App\Http;

final class Kernel
{
    protected $middleware = [
        \Fruitcake\Cors\HandleCors::class,
    ];
}
