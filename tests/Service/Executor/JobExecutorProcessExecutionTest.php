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
use Hyperf\XxlJob\Api\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Swoole\Coroutine;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\Executor\JobExecutorProcess
 */
class JobExecutorProcessExecutionTest extends TestCase
{
    private Config $config;

    private string $directory;

    private m\MockInterface&StdoutLoggerInterface $stdoutLogger;

    private ApiRequest&m\MockInterface $apiRequest;

    private JobExecutorLoggerInterface&m\MockInterface $jobExecutorLogger;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xxl_job_process_' . uniqid('', true);
        mkdir($this->directory, 0755, true);
        $this->config = new Config();
        $this->config->setLogFileDir($this->directory . DIRECTORY_SEPARATOR);
        $this->stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $this->stdoutLogger->shouldReceive('debug')->byDefault();
        $this->stdoutLogger->shouldReceive('warning')->byDefault();
        $this->stdoutLogger->shouldReceive('error')->byDefault();
        $this->apiRequest = m::mock(ApiRequest::class);
        $this->jobExecutorLogger = m::mock(JobExecutorLoggerInterface::class);
        $this->jobExecutorLogger->shouldReceive('warning')->byDefault();
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

    public function testChildReadsItsOwnLogIdBeforeExecution(): void
    {
        $capture = $this->directory . '/capture.json';
        $infoPath = $this->config->getLogFileDir() . 'jobId_1.info';
        $code = sprintf(
            '$info = json_decode((string) @file_get_contents(%s), true); file_put_contents(%s, json_encode($info)); usleep(100000);',
            var_export($infoPath, true),
            var_export($capture, true),
        );
        $this->config->setStartCommand([PHP_BINARY, '-r', $code]);
        $this->apiRequest->shouldNotReceive('callback');
        $request = $this->createRunRequest();
        JobRunContent::setJobId(1, $request);

        $this->createExecutor()->executeCommand($request);
        $this->waitForCompletion();

        self::assertFileExists($capture);
        $info = json_decode((string) file_get_contents($capture), true);
        self::assertSame(100, $info['logId']);
        self::assertSame(100, $info['runRequest']['logId']);
        self::assertSame('process', $info['executorType']);
        self::assertFileDoesNotExist($infoPath);
    }

    public function testNonZeroExitCallbacksFailure(): void
    {
        $this->config->setStartCommand([PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(3);']);
        $this->apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, m::pattern('/boom/'))
            ->andReturnNull();
        $request = $this->createRunRequest();
        JobRunContent::setJobId(1, $request);

        $this->createExecutor()->executeCommand($request);
        $this->waitForCompletion();

        self::assertFileDoesNotExist($this->config->getLogFileDir() . 'jobId_1.info');
    }

    public function testStartFailureCallbacksFailure(): void
    {
        $this->config->setStartCommand(['/definitely/missing/xxl-job-php']);
        $this->apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, m::type('string'))
            ->andReturnNull();
        $request = $this->createRunRequest();
        JobRunContent::setJobId(1, $request);

        $this->createExecutor()->executeCommand($request);
        $this->waitForCompletion();

        self::assertFileDoesNotExist($this->config->getLogFileDir() . 'jobId_1.info');
    }

    public function testTimeoutCallbacksFailure(): void
    {
        $this->config->setStartCommand([PHP_BINARY, '-r', 'sleep(3);']);
        $this->apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, 'scheduling center kill job. [job running, killed]')
            ->andReturnNull();
        $request = $this->createRunRequest();
        $request = RunRequest::create(array_merge($request->jsonSerialize(), ['executorTimeout' => 1]));
        JobRunContent::setJobId(1, $request);

        $this->createExecutor()->executeCommand($request);
        $this->waitForCompletion();
    }

    public function testNullPidProducesMeaningfulException(): void
    {
        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('process pid is unavailable');

        $this->createExecutor()->putJobFileInfo(null, $this->createRunRequest());
    }

    private function createExecutor(): JobExecutorProcess
    {
        return new JobExecutorProcess(
            m::mock(ContainerInterface::class),
            $this->stdoutLogger,
            $this->apiRequest,
            $this->jobExecutorLogger,
            $this->config,
            m::mock(EventDispatcherInterface::class),
        );
    }

    private function createRunRequest(): RunRequest
    {
        return RunRequest::create([
            'jobId' => 1,
            'logId' => 100,
            'logDateTime' => 123456,
            'executorHandler' => 'demo',
        ]);
    }

    private function waitForCompletion(): void
    {
        $deadline = microtime(true) + 6;
        while (JobRunContent::has(1) && microtime(true) < $deadline) {
            Coroutine::sleep(0.01);
        }
        self::assertFalse(JobRunContent::has(1), 'Process execution did not finish before the test deadline.');
    }
}
