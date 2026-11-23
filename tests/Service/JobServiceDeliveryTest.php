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

namespace HyperfTest\XxlJob\Service;

use Hyperf\Process\ProcessCollector;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Process\JobDispatcherProcess;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\JobService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Swoole\Coroutine\Socket;
use Swoole\Process;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\JobService
 */
class JobServiceDeliveryTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        $ref = new ReflectionClass(ProcessCollector::class);
        $property = $ref->getProperty('processes');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function testMissingDispatcherProcessFailsAndCallbacks(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, m::pattern('/dispatcher process is unavailable/'))
            ->andReturnNull();
        $service = $this->createDeliveryService($apiRequest);

        $this->expectException(GlueHandlerExecutionException::class);
        $service->deliver(new JobPipeMessage($this->createRunRequest()));
    }

    public function testFailedSocketSendIsNotSilent(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('callback')
            ->once()
            ->with(100, 123456, 500, m::pattern('/pipe send failed/'))
            ->andReturnNull();
        $service = $this->createDeliveryService($apiRequest);
        $socket = $this->registerDispatcherSocket();
        $socket->shouldReceive('send')->once()->andReturnFalse();

        $this->expectException(GlueHandlerExecutionException::class);
        $service->deliver(new JobPipeMessage($this->createRunRequest()));
    }

    public function testLargeMessageUsesTemporaryPayloadFile(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldNotReceive('callback');
        $service = $this->createDeliveryService($apiRequest);
        $socket = $this->registerDispatcherSocket();
        $envelope = null;
        $socket->shouldReceive('send')
            ->once()
            ->with(m::on(static function (string $payload) use (&$envelope): bool {
                $envelope = unserialize($payload);
                return strlen($payload) <= JobDispatcherProcess::RECV_LENGTH;
            }), 10)
            ->andReturnUsing(static fn (string $payload): int => strlen($payload));
        $request = $this->createRunRequest();
        $request = RunRequest::create(array_merge($request->jsonSerialize(), [
            'glueType' => 'GLUE_PHP',
            'glueSource' => str_repeat('x', JobDispatcherProcess::RECV_LENGTH),
        ]));

        $service->deliver(new JobPipeMessage($request));

        self::assertInstanceOf(JobPipeMessage::class, $envelope);
        self::assertNotNull($envelope->payloadFile);
        self::assertFileExists($envelope->payloadFile);
        $payloadFile = $envelope->payloadFile;
        $envelope->resolve();
        self::assertSame($request->getGlueSource(), $envelope->runRequest?->getGlueSource());
        self::assertSame(100, $envelope->runRequest?->getLogId());
        self::assertFileDoesNotExist($payloadFile);
    }

    public function testLargeMessageFileIsRemovedWhenSendFails(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('callback')->once()->andReturnNull();
        $service = $this->createDeliveryService($apiRequest);
        $socket = $this->registerDispatcherSocket();
        $envelope = null;
        $socket->shouldReceive('send')
            ->once()
            ->with(m::on(static function (string $payload) use (&$envelope): bool {
                $envelope = unserialize($payload);
                return true;
            }), 10)
            ->andReturnFalse();
        $request = RunRequest::create(array_merge($this->createRunRequest()->jsonSerialize(), [
            'glueSource' => str_repeat('x', JobDispatcherProcess::RECV_LENGTH),
        ]));

        try {
            $service->deliver(new JobPipeMessage($request));
            self::fail('Expected delivery failure.');
        } catch (GlueHandlerExecutionException) {
            self::assertInstanceOf(JobPipeMessage::class, $envelope);
            self::assertNotNull($envelope->payloadFile);
            self::assertFileDoesNotExist($envelope->payloadFile);
        }
    }

    private function createDeliveryService(ApiRequest $apiRequest): JobService
    {
        return new class(m::mock(ContainerInterface::class), m::mock(GlueHandlerManager::class), m::mock(JobHandlerManager::class), $apiRequest, new Config()) extends JobService {
            public function deliver(JobPipeMessage $message): void
            {
                $this->sendToDispatcher($message);
            }
        };
    }

    private function createRunRequest(): RunRequest
    {
        return RunRequest::create([
            'jobId' => 1,
            'logId' => 100,
            'logDateTime' => 123456,
            'glueType' => 'BEAN',
            'executorHandler' => 'demo',
        ]);
    }

    private function registerDispatcherSocket(): m\MockInterface&Socket
    {
        $socket = m::mock(Socket::class);
        $process = m::mock(Process::class);
        $process->shouldReceive('exportSocket')->once()->andReturn($socket);
        ProcessCollector::add(JobDispatcherProcess::JOB_DISPATCHER_NAME, $process);
        return $socket;
    }
}
