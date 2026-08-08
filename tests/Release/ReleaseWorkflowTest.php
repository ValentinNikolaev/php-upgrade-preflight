<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    public function testReleaseRequiresVerifiedAnnotatedTagFromMain(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');

        self::assertStringContainsString('tag_object_type', $workflow);
        self::assertStringContainsString('.verification.verified', $workflow);
        self::assertStringContainsString('git merge-base --is-ancestor', $workflow);
    }

    public function testQualityGateLintsWorkflowFiles(): void
    {
        $workflow = $this->readRootFile('.github/workflows/quality.yml');

        self::assertStringContainsString('rhysd/actionlint@sha256:', $workflow);
    }

    private function readRootFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertNotFalse($contents);

        return $contents;
    }
}
