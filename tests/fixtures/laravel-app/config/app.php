<?php

declare(strict_types=1);

return [
    'name' => 'PHP Upgrade Preflight Fixture',
    'env' => 'testing',
    'debug' => true,
    'url' => 'http://localhost',
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'cipher' => 'AES-256-CBC',
    'providers' => [
        Illuminate\Foundation\Providers\ArtisanServiceProvider::class,
    ],
    'aliases' => [],
];
