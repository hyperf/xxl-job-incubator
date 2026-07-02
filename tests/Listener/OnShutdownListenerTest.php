<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Listener\OnShutdownListener;
use Hyperf\Framework\Event\OnShutdown;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Listener\OnShutdownListener
 */
class OnShutdownListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testListenReturnsCorrectEvents(): void
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn(m::mock(StdoutLoggerInterface::class));
        $container->shouldReceive('get')->with(Config::class)->andReturn(new Config());

        $listener = new OnShutdownListener($container);
        $events = $listener->listen();

        $this->assertContains(OnShutdown::class, $events);
        $this->assertContains(\Hyperf\Server\Event\CoroutineServerStop::class, $events);
    }

    public function testProcessWhenDisabled(): void
    {
        $config = new Config();
        $config->setEnable(false);

        $logger = m::mock(StdoutLoggerInterface::class);
        $logger->shouldNotReceive('info');
        $logger->shouldNotReceive('error');

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($logger);
        $container->shouldReceive('get')->with(Config::class)->andReturn($config);

        $listener = new OnShutdownListener($container);

        // 不应抛出异常，不应尝试注销
        $listener->process(new OnShutdown(m::mock(\Swoole\Server::class)));
        $this->addToAssertionCount(1);
    }

    public function testProcessOnlyOnce(): void
    {
        $logger = m::mock(StdoutLoggerInterface::class);

        $config = new Config();
        $config->setEnable(true);
        $config->setAppName('test-app');
        $config->setClientUrl('http://localhost:9501/test');

        $response = m::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $apiRequest = m::mock(\Hyperf\XxlJob\ApiRequest::class);
        // 注销只应被调用一次
        $apiRequest->shouldReceive('registryRemove')->once()->andReturn($response);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($logger);
        $container->shouldReceive('get')->with(Config::class)->andReturn($config);
        $container->shouldReceive('get')->with(\Hyperf\XxlJob\ApiRequest::class)->andReturn($apiRequest);

        $logger->shouldReceive('info')->once();

        $listener = new OnShutdownListener($container);

        // 第一次调用
        $listener->process(new OnShutdown(m::mock(\Swoole\Server::class)));
        // 第二次调用应被 $this->processed 守卫跳过
        $listener->process(new OnShutdown(m::mock(\Swoole\Server::class)));
        $this->addToAssertionCount(1);
    }

    public function testProcessWhenRemoveFails(): void
    {
        $logger = m::mock(StdoutLoggerInterface::class);

        $config = new Config();
        $config->setEnable(true);
        $config->setAppName('test-app');
        $config->setClientUrl('http://localhost:9501/test');

        $response = m::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(500);

        $apiRequest = m::mock(\Hyperf\XxlJob\ApiRequest::class);
        $apiRequest->shouldReceive('registryRemove')->once()->andReturn($response);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($logger);
        $container->shouldReceive('get')->with(Config::class)->andReturn($config);
        $container->shouldReceive('get')->with(\Hyperf\XxlJob\ApiRequest::class)->andReturn($apiRequest);

        $logger->shouldReceive('error')->once();

        $listener = new OnShutdownListener($container);
        $listener->process(new OnShutdown(m::mock(\Swoole\Server::class)));
        $this->addToAssertionCount(1);
    }
}
