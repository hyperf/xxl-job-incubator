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
use Hyperf\XxlJob\Annotation\JobHandler;
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
        AnnotationCollector::collectClass('Foo', JobHandler::class, new JobHandler('foo'));
        AnnotationCollector::collectClass('Bar', JobHandler::class, new JobHandler('bar'));
        $listener = new BootAppRouteListener(m::mock(ContainerInterface::class), m::mock(Application::class));
        $listener = new ClassInvoker($listener);
        $listener->initAnnotationRoute();

        $this->assertSame('Foo', Application::getJobHandlers('foo'));
        $this->assertSame('Bar', Application::getJobHandlers('bar'));
    }
}
