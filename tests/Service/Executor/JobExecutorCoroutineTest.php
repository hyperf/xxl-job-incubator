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

namespace HyperfTest\XxlJob\Service\Executor;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\Api\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use RuntimeException;
use Swoole\Coroutine;
use Throwable;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine
 */
class JobExecutorCoroutineTest extends TestCase
{
    private m\MockInterface&StdoutLoggerInterface $stdoutLogger;

    private ApiRequest&m\MockInterface $apiRequest;

    private JobExecutorLoggerInterface&m\MockInterface $jobExecutorLogger;

    protected function setUp(): void
    {
        $this->stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $this->apiRequest = m::mock(ApiRequest::class);
        $this->jobExecutorLogger = m::mock(JobExecutorLoggerInterface::class);
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
    }

    public function testKillWithoutLocalCoroutineIdReturnsFalse(): void
    {
        $request = $this->createRunRequest();
        JobRunContent::setJobId(1, $request);
        $this->stdoutLogger->shouldReceive('error')->once()->with(m::pattern('/without a local coroutine id/'));
        $this->apiRequest->shouldNotReceive('callback');
        $executor = $this->createExecutor();

        self::assertFalse($executor->kill(1, 100, 'stop'));
    }

    public function testCallbackExceptionDoesNotEscapeKill(): void
    {
        $cid = Coroutine::create(static function (): void {
            try {
                Coroutine::sleep(30);
            } catch (Throwable) {
                // 原生测试协程需消化 cancel 异常；生产执行协程由 Hyperf 的协程边界统一捕获
            }
        });
        $request = $this->createRunRequest();
        $request->setExtension('cid', $cid);
        JobRunContent::setJobId(1, $request);
        $this->jobExecutorLogger->shouldReceive('warning')->once()->with('stop');
        $this->apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, 'stop')
            ->andThrow(new RuntimeException('admin down'));
        $this->stdoutLogger->shouldReceive('error')->once()->with('admin down');
        $executor = $this->createExecutor();

        self::assertTrue($executor->kill(1, 100, 'stop'));
        self::assertFalse(Coroutine::exists($cid));
    }

    public function testWatcherFailureCallbacksWithoutThrowing(): void
    {
        $this->stdoutLogger->shouldReceive('error')->once()->with(m::pattern('/kill failed/'));
        $this->apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, m::pattern('/kill failed/'))
            ->andReturnNull();
        $executor = new class(m::mock(ContainerInterface::class), $this->stdoutLogger, $this->apiRequest, $this->jobExecutorLogger, new Config(), m::mock(EventDispatcherInterface::class)) extends JobExecutorCoroutine {
            public function watch(RunRequest $request): void
            {
                $this->watchTimeout($request);
            }

            public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
            {
                throw new RuntimeException('kill failed');
            }
        };
        $request = $this->createRunRequest();
        // 使用组件支持的最短整数超时，触发 watcher 的终止失败分支
        $request = RunRequest::create(array_merge($request->jsonSerialize(), ['executorTimeout' => 1]));
        JobRunContent::setJobId(1, $request);

        $executor->watch($request);
        $this->addToAssertionCount(1);
    }

    private function createExecutor(): JobExecutorCoroutine
    {
        return new JobExecutorCoroutine(
            m::mock(ContainerInterface::class),
            $this->stdoutLogger,
            $this->apiRequest,
            $this->jobExecutorLogger,
            new Config(),
            m::mock(EventDispatcherInterface::class),
        );
    }

    private function createRunRequest(): RunRequest
    {
        return RunRequest::create([
            'jobId' => 1,
            'logId' => 100,
            'logDateTime' => 123456,
        ]);
    }
}
