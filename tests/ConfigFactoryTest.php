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

namespace HyperfTest\XxlJob;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\ConfigFactory;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\ConfigFactory
 */
class ConfigFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testInvokeCreatesConfigWithDefaults(): void
    {
        $hyperfConfig = m::mock(ConfigInterface::class);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.enable')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.app_name')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.access_token')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.admin_address')->andReturn('http://127.0.0.1:8080/xxl-job-admin');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.heartbeat')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.host')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.port')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.prefix_url')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.execution_mode')->andReturn(null);
        $hyperfConfig->shouldReceive('has')->with('xxl_job.guzzle.config')->andReturn(false);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.log_retention_days')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.max_process_lifetime')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.start_command')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.file_logger.dir')->andReturn(null);

        $stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $stdoutLogger->shouldReceive('warning')
            ->once()
            ->with('xxl_job.access_token is not configured. All executor requests will be rejected.');

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn($hyperfConfig);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($stdoutLogger);

        $factory = new ConfigFactory();
        /** @var Config $config */
        $config = $factory($container);

        $this->assertInstanceOf(Config::class, $config);
        $this->assertFalse($config->isEnable());
        $this->assertSame('', $config->getAppName());
        $this->assertSame('http://127.0.0.1:8080', $config->getBaseUri());
        $this->assertSame('/xxl-job-admin', $config->getServerUrlPath());
        $this->assertSame(30, $config->getHeartbeat());
        $this->assertSame(9501, $config->getExecutorServerPort());
        $this->assertSame(-1, $config->getLogRetentionDays());
    }

    public function testInvokeWithCustomValues(): void
    {
        $hyperfConfig = m::mock(ConfigInterface::class);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.enable')->andReturn(true);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.app_name')->andReturn('my-app');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.access_token')->andReturn('tok123');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.admin_address')->andReturn('http://admin.example.com:9090/admin-path');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.heartbeat')->andReturn(60);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.host')->andReturn('10.0.0.1');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.port')->andReturn(8080);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.prefix_url')->andReturn('my-executor');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.execution_mode')->andReturn('process');
        $hyperfConfig->shouldReceive('has')->with('xxl_job.guzzle.config')->andReturn(false);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.log_retention_days')->andReturn(7);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.max_process_lifetime')->andReturn(86400);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.start_command')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.file_logger.dir')->andReturn(null);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn($hyperfConfig);

        $factory = new ConfigFactory();
        /** @var Config $config */
        $config = $factory($container);

        $this->assertTrue($config->isEnable());
        $this->assertSame('my-app', $config->getAppName());
        $this->assertSame('tok123', $config->getAccessToken());
        $this->assertSame('http://admin.example.com:9090', $config->getBaseUri());
        $this->assertSame('/admin-path', $config->getServerUrlPath());
        $this->assertSame(60, $config->getHeartbeat());
        $this->assertSame('10.0.0.1', $config->getExecutorServerHost());
        $this->assertSame(8080, $config->getExecutorServerPort());
        $this->assertSame('my-executor', $config->getExecutorServerPrefixUrl());
        $this->assertSame('process', $config->getExecutionMode());
        $this->assertSame(7, $config->getLogRetentionDays());
        $this->assertSame(86400, $config->getMaxProcessLifetime());
    }

    public function testInvokeWithGuzzleConfig(): void
    {
        $hyperfConfig = m::mock(ConfigInterface::class);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.enable')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.app_name')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.access_token')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.admin_address')->andReturn('http://127.0.0.1:8080/xxl-job-admin');
        $hyperfConfig->shouldReceive('get')->with('xxl_job.heartbeat')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.host')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.port')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.executor_server.prefix_url')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.execution_mode')->andReturn(null);
        $hyperfConfig->shouldReceive('has')->with('xxl_job.guzzle.config')->andReturn(true);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.guzzle.config')->andReturn(['timeout' => 99, 'headers' => ['X-Custom' => 'Val']]);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.log_retention_days')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.max_process_lifetime')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.start_command')->andReturn(null);
        $hyperfConfig->shouldReceive('get')->with('xxl_job.file_logger.dir')->andReturn(null);

        $stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $stdoutLogger->shouldReceive('warning')
            ->once()
            ->with('xxl_job.access_token is not configured. All executor requests will be rejected.');

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn($hyperfConfig);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($stdoutLogger);

        $factory = new ConfigFactory();
        /** @var Config $config */
        $config = $factory($container);

        $guzzle = $config->getGuzzleConfig();
        $this->assertSame(99, $guzzle['timeout']);
        $this->assertSame('Val', $guzzle['headers']['X-Custom']);
    }
}
