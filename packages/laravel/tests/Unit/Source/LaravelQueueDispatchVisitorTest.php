<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Source;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpUpgradePreflight\Laravel\Source\LaravelSourceUsageVisitor;
use PHPUnit\Framework\TestCase;

final class LaravelQueueDispatchVisitorTest extends TestCase
{
    /** @dataProvider queueDispatchProvider */
    public function testQueueDispatchRequiresTheLaravelFacade(string $source, int $expected): void
    {
        $factory = new ParserFactory();
        $factoryReflection = new \ReflectionObject($factory);
        $parser = $factoryReflection->hasMethod('createForNewestSupportedVersion')
            ? $factoryReflection->getMethod('createForNewestSupportedVersion')->invoke($factory)
            : $factoryReflection->getMethod('create')->invoke($factory, constant(ParserFactory::class . '::PREFER_PHP7'));
        self::assertInstanceOf(Parser::class, $parser);
        $visitor = new LaravelSourceUsageVisitor('app/Jobs/Example.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($parser->parse($source) ?? []);

        self::assertCount($expected, array_filter($visitor->usages(), static fn (array $usage): bool => $usage['usage_type'] === 'deprecated_queue_dispatch'));
    }

    /** @return iterable<string, array{string, int}> */
    public function queueDispatchProvider(): iterable
    {
        yield 'canonical facade' => ['<?php use Illuminate\Support\Facades\Bus; Bus::dispatchNow($job);', 1];
        yield 'import alias resolves' => ['<?php use Illuminate\Support\Facades\Bus as JobBus; JobBus::dispatchNow($job);', 1];
        yield 'fully qualified facade' => ['<?php \Illuminate\Support\Facades\Bus::dispatchNow($job);', 1];
        yield 'global Laravel facade alias' => ['<?php Bus::dispatchNow($job);', 1];
        yield 'unrelated facade named Bus' => ['<?php use Vendor\Facades\Bus; Bus::dispatchNow($job);', 0];
        yield 'different namespace named Bus' => ['<?php namespace App; use App\Facades\Bus; Bus::dispatchNow($job);', 0];
        yield 'replacement method' => ['<?php use Illuminate\Support\Facades\Bus; Bus::dispatchSync($job);', 0];
    }
}
