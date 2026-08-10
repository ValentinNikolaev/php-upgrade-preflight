<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

final class LegacyCsrfTest
{
    public function disableLegacyMiddleware($testCase): void
    {
        $testCase->withoutMiddleware([VerifyCsrfToken::class]);
    }
}
