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

use FilesystemIterator;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\BaseService;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Hyperf\XxlJob\Service\Executor\JobRunInfoStore;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\BaseService
 * @covers \Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine
 * @covers \Hyperf\XxlJob\Service\Executor\JobExecutorProcess
 * @covers \Hyperf\XxlJob\Service\Executor\JobRunInfoStore
 */
class JobRunInfoStoreTest extends TestCase
{
    private Config $config;

    private JobRunInfoStore $store;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xxl_job_info_' . uniqid('', true);
        mkdir($this->directory, 0755, true);
        $this->config = new Config();
        $this->config->setLogFileDir($this->directory . DIRECTORY_SEPARATOR);
        $this->store = new JobRunInfoStore($this->config);
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
        foreach (new FilesystemIterator($this->directory, FilesystemIterator::SKIP_DOTS) as $file) {
            unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testCoroutineStateIsVisibleWithoutLocalContent(): void
    {
        $request = $this->createRunRequest();
        $request->setExtension('cid', 123);
        $this->store->put($request, JobRunInfoStore::TYPE_COROUTINE);
        $executor = $this->createCoroutineExecutor();

        self::assertFalse(JobRunContent::has(1));
        self::assertTrue($executor->isRun(1));
        $raw = json_decode((string) file_get_contents($this->store->getFilePath(1)), true);
        self::assertSame('coroutine', $raw['executorType']);
        self::assertSame(100, $raw['logId']);
        self::assertArrayNotHasKey('_extension', $raw['runRequest']);
    }

    public function testStaleCoroutineStateIsRemoved(): void
    {
        $this->store->put($this->createRunRequest(), JobRunInfoStore::TYPE_COROUTINE);
        $this->rewriteInfo(['ownerPid' => 999999, 'ownerStartTime' => null]);
        $executor = $this->createCoroutineExecutor();

        self::assertFalse($executor->isRun(1));
        self::assertFileDoesNotExist($this->store->getFilePath(1));
    }

    public function testCoroutineKillWithoutOwnerRegistrationReturnsFalse(): void
    {
        $this->store->put($this->createRunRequest(), JobRunInfoStore::TYPE_COROUTINE);
        $logger = m::mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(m::pattern('/not running in the current process/'));
        $executor = $this->createCoroutineExecutor($logger);

        self::assertFalse($executor->kill(1, 100));
        self::assertFileExists($this->store->getFilePath(1));
    }

    public function testRemoteProcessKillUsesSharedPid(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'sleep(30);']);
        $process->start();
        try {
            $pid = $process->getPid();
            self::assertIsInt($pid);
            $this->store->put($this->createRunRequest(), JobRunInfoStore::TYPE_PROCESS, $pid);
            $executor = $this->createProcessExecutor();
            self::assertFalse(JobRunContent::has(1));

            self::assertTrue($executor->kill(1, 100));
            try {
                $process->wait();
            } catch (ProcessSignaledException) {
                // kill -9 是本轮验证的预期退出方式
            }
            self::assertFalse($process->isRunning());
            self::assertFileDoesNotExist($this->store->getFilePath(1));
        } finally {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    public function testRemoteProcessKillRejectsReusedPidIdentity(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'sleep(30);']);
        $process->start();
        try {
            $pid = $process->getPid();
            self::assertIsInt($pid);
            $this->store->put($this->createRunRequest(), JobRunInfoStore::TYPE_PROCESS, $pid);
            $info = $this->store->get(1);
            $this->rewriteInfo(['pidStartTime' => $info['pidStartTime'] + 1]);
            $logger = m::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('error')->once()->with(m::pattern('/refusing to kill a reused pid/'));
            $executor = $this->createProcessExecutor($logger);

            self::assertFalse($executor->kill(1, 100));
            self::assertTrue($process->isRunning());
        } finally {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    public function testConditionalRemoveKeepsNewLogId(): void
    {
        $old = $this->createRunRequest(100);
        $new = $this->createRunRequest(200);
        $this->store->put($old, JobRunInfoStore::TYPE_PROCESS);
        $this->store->put($new, JobRunInfoStore::TYPE_PROCESS);

        $this->store->remove(1, 100);

        self::assertSame(200, $this->store->get(1)['logId']);
        self::assertFileExists($this->store->getFilePath(1));
    }

    public function testBaseServiceRoutesBySharedExecutorType(): void
    {
        $coroutine = m::mock(JobExecutorCoroutine::class);
        $coroutine->shouldReceive('isRun')->once()->with(1)->andReturnTrue();
        $process = m::mock(JobExecutorProcess::class);
        $process->shouldReceive('isRun')->once()->with(1)->andReturnTrue();
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->once()->with(JobExecutorCoroutine::class)->andReturn($coroutine);
        $container->shouldReceive('get')->once()->with(JobExecutorProcess::class)->andReturn($process);
        $service = new BaseService(
            $container,
            m::mock(GlueHandlerManager::class),
            m::mock(JobHandlerManager::class),
            m::mock(ApiRequest::class),
            $this->config,
        );

        $this->store->put($this->createRunRequest(), JobRunInfoStore::TYPE_COROUTINE);
        self::assertTrue($service->isRun(1));
        $this->store->put($this->createRunRequest(200), JobRunInfoStore::TYPE_PROCESS);
        self::assertTrue($service->isRun(1));
    }

    private function createCoroutineExecutor(?StdoutLoggerInterface $logger = null): JobExecutorCoroutine
    {
        return new JobExecutorCoroutine(
            m::mock(ContainerInterface::class),
            $logger ?? m::mock(StdoutLoggerInterface::class),
            m::mock(ApiRequest::class),
            m::mock(JobExecutorLoggerInterface::class),
            $this->config,
            m::mock(EventDispatcherInterface::class),
        );
    }

    private function createProcessExecutor(?StdoutLoggerInterface $logger = null): JobExecutorProcess
    {
        return new JobExecutorProcess(
            m::mock(ContainerInterface::class),
            $logger ?? m::mock(StdoutLoggerInterface::class),
            m::mock(ApiRequest::class),
            m::mock(JobExecutorLoggerInterface::class),
            $this->config,
            m::mock(EventDispatcherInterface::class),
        );
    }

    private function createRunRequest(int $logId = 100): RunRequest
    {
        return RunRequest::create([
            'jobId' => 1,
            'logId' => $logId,
            'logDateTime' => 123456,
            'executorHandler' => 'demo',
        ]);
    }

    private function rewriteInfo(array $changes): void
    {
        $path = $this->store->getFilePath(1);
        $info = json_decode((string) file_get_contents($path), true);
        file_put_contents($path, json_encode(array_merge($info, $changes)));
    }
}
