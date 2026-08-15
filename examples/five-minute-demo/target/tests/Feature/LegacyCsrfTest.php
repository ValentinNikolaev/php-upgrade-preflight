<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

final class LegacyCsrfTest
{
    public function disableLegacyMiddleware(object $testCase): void
    {
        $testCase->withoutMiddleware([VerifyCsrfToken::class]);
    }
}
