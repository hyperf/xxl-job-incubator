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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Reflection\ClassInvoker;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Dispatcher\XxlJobRoute;
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

    public function testInitAnnotationRouteWithMethodBean()
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Config::class)->andReturn(m::mock(Config::class));
        $container->shouldReceive('get')->with(XxlJobRoute::class)->andReturn(m::mock(XxlJobRoute::class));
        $container->shouldReceive('get')->with(DispatcherFactory::class)->andReturn(m::mock(DispatcherFactory::class));
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn(m::mock(ConfigInterface::class));
        $jobHandlerManager = new JobHandlerManager();
        $container->shouldReceive('get')->with(JobHandlerManager::class)->andReturn($jobHandlerManager);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn(m::mock(StdoutLoggerInterface::class));
        $container->shouldReceive('get')->with(BarJobClass::class)->andReturn(m::mock(BarJobClass::class));

        $listener = new BootAppRouteListener($container);
        $listener = new ClassInvoker($listener);

        // Method mode with init and destroy
        AnnotationCollector::collectMethod('Foo', 'fooDemo', XxlJob::class, new XxlJob('foo-method-1', 'init', 'destroy'));
        $listener->initAnnotationRoute();
        $jobHandlerDefinition = $jobHandlerManager->getJobHandlers('foo-method-1');
        $this->assertSame('Foo', $jobHandlerDefinition->getClass());
        $this->assertSame('fooDemo', $jobHandlerDefinition->getMethod());
        $this->assertSame('init', $jobHandlerDefinition->getInit());
        $this->assertSame('destroy', $jobHandlerDefinition->getDestroy());

        // Method mode without init and destroy
        AnnotationCollector::collectMethod('Foo', 'fooDemo', XxlJob::class, new XxlJob('foo-method-2', '', ''));
        $listener->initAnnotationRoute();
        $jobHandlerDefinition = $jobHandlerManager->getJobHandlers('foo-method-2');
        $this->assertSame('Foo', $jobHandlerDefinition->getClass());
        $this->assertSame('fooDemo', $jobHandlerDefinition->getMethod());
        $this->assertSame('', $jobHandlerDefinition->getInit());
        $this->assertSame('', $jobHandlerDefinition->getDestroy());

        // Class moed
        AnnotationCollector::collectClass(BarJobClass::class, XxlJob::class, new XxlJob('bar-job-class-1', 'execute', '', ''));
        $listener->initAnnotationRoute();
        $jobHandlerDefinition = $jobHandlerManager->getJobHandlers('bar-job-class-1');
        $this->assertSame(BarJobClass::class, $jobHandlerDefinition->getClass());
        $this->assertSame('execute', $jobHandlerDefinition->getMethod());
        $this->assertSame('init', $jobHandlerDefinition->getInit());
        $this->assertSame('destroy', $jobHandlerDefinition->getDestroy());
    }
}
