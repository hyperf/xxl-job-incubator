<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\XxlJob\Listener;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\Reflection\ClassInvoker;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @coversNothing
 */
class BootAppRouteListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        AnnotationCollector::clear();
    }

    public function testInitAnnotationRoute()
    {
        AnnotationCollector::collectMethod('Foo', 'fooDemo', XxlJob::class, new XxlJob('foo', 'init', 'destroy'));
        AnnotationCollector::collectMethod('Bar', 'barDemo', XxlJob::class, new XxlJob('bar'));
        $listener = new BootAppRouteListener(m::mock(ContainerInterface::class), m::mock(Application::class));
        $listener = new ClassInvoker($listener);
        $listener->initAnnotationRoute();

        $this->assertSame('Foo', Application::getJobHandlers('foo')['class']);
        $this->assertSame('fooDemo', Application::getJobHandlers('foo')['method']);
        $this->assertSame('init', Application::getJobHandlers('foo')['init']);
        $this->assertSame('destroy', Application::getJobHandlers('foo')['destroy']);

        $this->assertSame('Bar', Application::getJobHandlers('bar')['class']);
        $this->assertSame('barDemo', Application::getJobHandlers('bar')['method']);
        $this->assertSame('', Application::getJobHandlers('bar')['init']);
        $this->assertSame('', Application::getJobHandlers('bar')['destroy']);
    }
}
