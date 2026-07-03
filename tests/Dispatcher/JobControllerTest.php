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

namespace HyperfTest\XxlJob\Dispatcher;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponseContract;
use Hyperf\XxlJob\Dispatcher\JobController;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Logger\LogContent;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\JobService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Dispatcher\JobController
 */
class JobControllerTest extends TestCase
{
    protected JobController $controller;

    protected ContainerInterface&m\MockInterface $container;

    protected m\MockInterface&StdoutLoggerInterface $stdoutLogger;

    protected JobExecutorLoggerInterface&m\MockInterface $jobExecutorLogger;

    protected JobService&m\MockInterface $jobService;

    protected m\MockInterface&ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->container = m::mock(ContainerInterface::class);
        $this->stdoutLogger = m::mock(StdoutLoggerInterface::class);
        $this->jobExecutorLogger = m::mock(JobExecutorLoggerInterface::class);
        $this->jobService = m::mock(JobService::class);
        $this->request = m::mock(ServerRequestInterface::class);

        $this->controller = new JobController(
            $this->container,
            $this->stdoutLogger,
            $this->jobExecutorLogger,
            $this->jobService,
        );
    }

    protected function tearDown(): void
    {
        m::close();
    }

    // ─── run() ────────────────────────────────────────────────

    public function testRunSuccess(): void
    {
        $this->mockInput([
            'jobId' => 1,
            'logId' => 100,
            'glueType' => 'BEAN',
            'executorHandler' => 'demoHandler',
        ]);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('debug')->once();
        $this->jobService->shouldReceive('executorBlockStrategy')
            ->with(m::type(RunRequest::class))
            ->once();

        $response = $this->controller->run();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testRunCatchesXxlJobException(): void
    {
        $this->mockInput(['jobId' => 1, 'logId' => 100, 'glueType' => 'BEAN', 'executorHandler' => 'demoHandler']);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('debug')->once();
        $this->stdoutLogger->shouldReceive('warning')->once();
        $this->jobService->shouldReceive('executorBlockStrategy')
            ->once()
            ->andThrow(new XxlJobException('handler not found'));

        $response = $this->controller->run();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testRunCatchesGenericThrowable(): void
    {
        $this->mockInput(['jobId' => 1, 'logId' => 100, 'glueType' => 'BEAN', 'executorHandler' => 'demoHandler']);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('debug')->once();
        $this->stdoutLogger->shouldReceive('error')->once();
        $this->jobService->shouldReceive('executorBlockStrategy')
            ->once()
            ->andThrow(new RuntimeException('unexpected error'));

        $response = $this->controller->run();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    // ─── beat() ───────────────────────────────────────────────

    public function testBeat(): void
    {
        $this->mockResponse();
        $response = $this->controller->beat();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    // ─── idleBeat() ───────────────────────────────────────────

    public function testIdleBeatJobRunning(): void
    {
        $this->mockInput(['jobId' => 42]);
        $this->mockResponse();

        $this->jobService->shouldReceive('isRun')->with(42)->once()->andReturn(true);

        $response = $this->controller->idleBeat();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testIdleBeatJobNotRunning(): void
    {
        $this->mockInput(['jobId' => 42]);
        $this->mockResponse();

        $this->jobService->shouldReceive('isRun')->with(42)->once()->andReturn(false);

        $response = $this->controller->idleBeat();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testIdleBeatMissingJobId(): void
    {
        $this->mockInput([]); // no jobId
        $this->mockResponse();

        $this->jobService->shouldReceive('isRun')->with(0)->once()->andReturn(false);

        $response = $this->controller->idleBeat();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testIdleBeatCatchesThrowable(): void
    {
        $this->mockInput(['jobId' => 1]);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('error')->once();
        $this->jobService->shouldReceive('isRun')->once()->andThrow(new RuntimeException('boom'));

        $response = $this->controller->idleBeat();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    // ─── kill() ───────────────────────────────────────────────

    public function testKillSuccess(): void
    {
        $this->mockInput(['jobId' => 99]);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('info')->once();
        $this->jobService->shouldReceive('send')->with(null, 99)->once();

        $response = $this->controller->kill();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testKillCatchesThrowable(): void
    {
        $this->mockInput(['jobId' => 99]);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('error')->once();
        $this->jobService->shouldReceive('send')->once()->andThrow(new RuntimeException('kill failed'));

        $response = $this->controller->kill();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    // ─── log() ────────────────────────────────────────────────

    public function testLogReturnsSuccessWhenFileExists(): void
    {
        $this->mockInput([
            'logDateTim' => 20240101120000,
            'logId' => 500,
            'fromLineNum' => 0,
        ]);
        $this->mockResponse();

        $logContent = new LogContent("line1\nline2", 2, true);
        $this->jobExecutorLogger->shouldReceive('retrieveLog')
            ->with(500, 20240101120000, 0, -1)
            ->once()
            ->andReturn($logContent);

        $response = $this->controller->log();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testLogReturnsErrorWhenFileNotFound(): void
    {
        $this->mockInput([
            'logDateTim' => 20240101120000,
            'logId' => 999,
            'fromLineNum' => 0,
        ]);
        $this->mockResponse();

        $logContent = new LogContent('', 0, false);
        $this->jobExecutorLogger->shouldReceive('retrieveLog')
            ->once()
            ->andReturn($logContent);

        $response = $this->controller->log();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    public function testLogCatchesThrowable(): void
    {
        $this->mockInput(['logDateTim' => 1, 'logId' => 1, 'fromLineNum' => 0]);
        $this->mockResponse();

        $this->stdoutLogger->shouldReceive('error')->once();
        $this->jobExecutorLogger->shouldReceive('retrieveLog')
            ->once()
            ->andThrow(new RuntimeException('disk full'));

        $response = $this->controller->log();
        $this->assertInstanceOf(HttpResponseContract::class, $response);
    }

    protected function mockInput(array $data): void
    {
        $this->request->shouldReceive('getParsedBody')->andReturn($data);
        $this->container->shouldReceive('get')
            ->with(ServerRequestInterface::class)
            ->andReturn($this->request);
    }

    protected function mockResponse(): void
    {
        $response = m::mock(HttpResponseContract::class);
        $response->shouldReceive('withAddedHeader')
            ->with('content-type', 'application/json')
            ->andReturnSelf();
        $response->shouldReceive('withBody')
            ->andReturnSelf();
        $this->container->shouldReceive('get')
            ->with(HttpResponseContract::class)
            ->andReturn($response);
    }
}
