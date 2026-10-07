<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Support;

use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor;
use PHPUnit\Framework\TestCase;

final class PrivacyPhpRuntimeCompatibilityTest extends TestCase
{
    public function testCyclicAndRepeatedObjectsStaySanitizedWithoutRuntimeDiagnostics(): void
    {
        $object = new \stdClass();
        $object->path = '/private/project/src/File.php';
        $object->token = 'private-test-token';
        $object->self = $object;
        $diagnostics = [];
        set_error_handler(static function (int $level, string $message) use (&$diagnostics): bool {
            $diagnostics[] = [$level, $message];

            return true;
        }, E_ALL);
        try {
            $redacted = SensitiveOutputRedactor::redactStructured([$object, $object]);
            $sanitized = PathExposurePolicy::sanitizeCanonicalReport(['objects' => [$object, $object]], '/private/project');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $diagnostics);
        self::assertIsArray($redacted);
        foreach ($redacted as $item) {
            self::assertSame(SensitiveOutputRedactor::REDACTED, $item->token);
            self::assertSame(SensitiveOutputRedactor::REDACTED, $item->self);
        }
        foreach ($sanitized['objects'] as $item) {
            self::assertSame('[PROJECT_ROOT]/src/File.php', $item->path);
            self::assertSame(SensitiveOutputRedactor::REDACTED, $item->token);
            self::assertSame(SensitiveOutputRedactor::REDACTED, $item->self);
        }
    }
}
