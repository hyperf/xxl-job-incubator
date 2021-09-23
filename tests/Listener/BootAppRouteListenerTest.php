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
use Hyperf\XxlJob\Annotation\JobHandler;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Hyperf\XxlJob\Logger\XxlJobHelper;
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
        AnnotationCollector::collectClass(BarJobClass::class, JobHandler::class, new JobHandler('bar'));
        $container = m::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('get')->with(Application::class)->andReturn(m::mock(Application::class));
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn(m::mock(StdoutLoggerInterface::class));
        $container->shouldReceive('get')->with(XxlJobHelper::class)->andReturn(m::mock(XxlJobHelper::class));
        $container->shouldReceive('get')->with(BarJobClass::class)->andReturn(m::mock(BarJobClass::class));

        $listener = new BootAppRouteListener($container);
        $listener = new ClassInvoker($listener);
        $listener->initAnnotationRoute();

        $this->assertSame('Foo', Application::getJobHandlers('foo')['class']);
        $this->assertSame('fooDemo', Application::getJobHandlers('foo')['method']);
        $this->assertSame('init', Application::getJobHandlers('foo')['init']);
        $this->assertSame('destroy', Application::getJobHandlers('foo')['destroy']);

        $this->assertSame(BarJobClass::class, Application::getJobHandlers('bar')['class']);
        $this->assertSame('execute', Application::getJobHandlers('bar')['method']);
        $this->assertSame('', Application::getJobHandlers('bar')['init']);
        $this->assertSame('', Application::getJobHandlers('bar')['destroy']);
    }
}
