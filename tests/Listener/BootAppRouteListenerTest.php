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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Reflection\ClassInvoker;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use HyperfTest\XxlJob\BarJobClass;
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
        AnnotationCollector::clear();
        AnnotationCollector::collectMethod('Foo', 'fooDemo', XxlJob::class, new XxlJob('foo', 'init', 'destroy'));
        AnnotationCollector::collectClass(BarJobClass::class, XxlJob::class, new XxlJob('bar', 'execute', 'init', 'destory'));
        $container = m::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('get')->with(JobHandlerManager::class)->andReturn(m::mock(JobHandlerManager::class));
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn(m::mock(StdoutLoggerInterface::class));
        $container->shouldReceive('get')->with(BarJobClass::class)->andReturn(m::mock(BarJobClass::class));

        $listener = new BootAppRouteListener($container);
        $listener = new ClassInvoker($listener);
        $listener->initAnnotationRoute();

        $this->assertSame('Foo', JobHandlerManager::getJobHandlers('foo')['class']);
        $this->assertSame('fooDemo', JobHandlerManager::getJobHandlers('foo')['method']);
        $this->assertSame('init', JobHandlerManager::getJobHandlers('foo')['init']);
        $this->assertSame('destroy', JobHandlerManager::getJobHandlers('foo')['destroy']);

        $this->assertSame('Bar', JobHandlerManager::getJobHandlers('bar')['class']);
        $this->assertSame('barDemo', JobHandlerManager::getJobHandlers('bar')['method']);
        $this->assertSame('', JobHandlerManager::getJobHandlers('bar')['init']);
        $this->assertSame('', JobHandlerManager::getJobHandlers('bar')['destroy']);

        $this->assertSame(BarJobClass::class, JobHandlerManager::getJobHandlers('bar')['class']);
        $this->assertSame('execute', JobHandlerManager::getJobHandlers('bar')['method']);
        $this->assertSame('init', JobHandlerManager::getJobHandlers('bar')['init']);
        $this->assertSame('destory', JobHandlerManager::getJobHandlers('bar')['destroy']);
    }
}
