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

namespace HyperfTest\XxlJob\Process;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\ProcessManager;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Process\JobDispatcherProcess;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Process\JobDispatcherProcess
 */
class JobDispatcherProcessTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        ProcessManager::setRunning(false);
        JobDispatcherProcess::setAcceptingJobs(true);
    }

    public function testGracefulShutdownHasDeadlineAndFailsPendingJobs(): void
    {
        $config = new Config();
        $config->setMaxProcessLifetime(1);
        $serialService = m::mock(JobSerialExecutionService::class);
        $serialService->shouldReceive('hasPendingJobs')->andReturnTrue();
        $serialService->shouldReceive('failPendingJobs')
            ->once()
            ->with('XXL-JOB dispatcher exceeded graceful shutdown timeout; job not completed.');
        $logger = m::mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('warning')->once();
        $logger->shouldReceive('error')->byDefault();
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->andReturnFalse();
        $container->shouldReceive('get')->with(Config::class)->andReturn($config);
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($logger);
        $container->shouldReceive('get')->with(JobSerialExecutionService::class)->andReturn($serialService);
        $process = new class($container) extends JobDispatcherProcess {
            protected int $shutdownTimeout = 1;
        };
        ProcessManager::setRunning(true);
        $startedAt = microtime(true);

        $process->handle();

        self::assertLessThan(5.0, microtime(true) - $startedAt);
        self::assertFalse(JobDispatcherProcess::isAcceptingJobs());
    }
}
