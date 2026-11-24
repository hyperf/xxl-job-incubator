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

namespace HyperfTest\XxlJob\Glue\Handlers;

use FilesystemIterator;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\Api\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Event\AfterJobRun;
use Hyperf\XxlJob\Event\BeforeJobRun;
use Hyperf\XxlJob\Glue\Handlers\ScriptHandler;
use Hyperf\XxlJob\Handler\JobHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Glue\Handlers\ScriptHandler
 * @covers \Hyperf\XxlJob\Service\Executor\AbstractJobExecutor
 */
class ScriptHandlerTimeoutTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xxl_job_timeout_' . uniqid('', true);
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        m::close();
        $ref = new ReflectionClass(JobRunContent::class);
        foreach (['content', 'channels'] as $name) {
            $property = $ref->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
        $remove = function (string $path) use (&$remove): void {
            if (! is_dir($path)) {
                return;
            }
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
                $file->isDir() ? $remove($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($path);
        };
        $remove($this->directory);
    }

    public function testTimeoutKeepsDedicatedExceptionAndCallbackMessage(): void
    {
        $config = new Config();
        $config->setLogFileDir($this->directory . '/logs/');
        $stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $stdoutLogger->shouldReceive('warning')->once();
        $stdoutLogger->shouldNotReceive('error');
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, 'scheduling center kill job. [job running, killed]')
            ->andReturnNull();
        $jobLogger = m::mock(JobExecutorLoggerInterface::class);
        $jobLogger->shouldReceive('info')->byDefault();
        $jobLogger->shouldReceive('warning')->once()->with('scheduling center kill job. [job running, killed]');
        $jobLogger->shouldNotReceive('error');
        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->once()->with(m::type(BeforeJobRun::class));
        $eventDispatcher->shouldReceive('dispatch')->once()->with(m::type(AfterJobRun::class));
        $container = m::mock(ContainerInterface::class);
        // 命中通用 Throwable 分支才会读取 Formatter；本测试必须避开该分支
        $container->shouldNotReceive('has');
        $processExecutor = new JobExecutorProcess($container, $stdoutLogger, $apiRequest, $jobLogger, $config, $eventDispatcher);
        $handlerContainer = m::mock(ContainerInterface::class);
        $handlerContainer->shouldReceive('get')->once()->with(Config::class)->andReturn($config);
        $coroutineExecutor = new JobExecutorCoroutine($container, $stdoutLogger, $apiRequest, $jobLogger, $config, $eventDispatcher);
        $handler = new class($handlerContainer, m::mock(JobHandlerManager::class), $jobLogger, $stdoutLogger, $processExecutor, $coroutineExecutor) extends ScriptHandler {
            public function executeCommand(string $filePath, RunRequest $request): void
            {
                $this->executeCmd($filePath, $request);
            }
        };
        $script = $this->directory . '/sleep.sh';
        file_put_contents($script, 'sleep 3');
        $request = RunRequest::create([
            'jobId' => 1,
            'logId' => 100,
            'logDateTime' => 123456,
            'glueType' => 'GLUE_SHELL',
            'executorTimeout' => 1,
        ]);
        JobRunContent::setJobId(1, $request);

        $coroutineExecutor->execute($request, static function (RunRequest $request) use ($handler, $script): void {
            $handler->executeCommand($script, $request);
        });

        self::assertFalse(JobRunContent::has(1));
        self::assertFileDoesNotExist($config->getLogFileDir() . 'jobId_1.info');
    }
}
