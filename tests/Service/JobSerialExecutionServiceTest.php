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

use Hyperf\Engine\Channel;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\JobSerialExecutionService
 */
class JobSerialExecutionServiceTest extends TestCase
{
    private ContainerInterface&m\MockInterface $container;

    private GlueHandlerManager&m\MockInterface $glueHandlerManager;

    private JobHandlerManager&m\MockInterface $jobHandlerManager;

    private ApiRequest&m\MockInterface $apiRequest;

    private Config $config;

    protected function setUp(): void
    {
        $this->container = m::mock(ContainerInterface::class);
        $this->glueHandlerManager = m::mock(GlueHandlerManager::class);
        $this->jobHandlerManager = m::mock(JobHandlerManager::class);
        $this->apiRequest = m::mock(ApiRequest::class);
        $this->config = new Config();
    }

    protected function tearDown(): void
    {
        m::close();
        $this->resetJobRunContent();
    }

    // ──────────────────────────────────────────────
    //  Kill 路径测试
    // ──────────────────────────────────────────────

    /**
     * killJobId > 0 且 JobRunContent 中存在对应 job：
     * 应依次调用 sendKillMsg、remove、kill。
     */
    public function testKillJobWithRunningTask(): void
    {
        $runRequest = $this->createRunRequest(99);
        JobRunContent::setJobId(99, $runRequest);

        $service = $this->createService();

        $service->shouldReceive('sendKillMsg')->with(99)->once()->globally()->ordered();
        $service->shouldReceive('remove')->with(99)->once()->globally()->ordered();
        $service->shouldReceive('kill')
            ->with(99, 100, 'Job toStop, stopReason:scheduling center kill job.')
            ->once()->globally()->ordered();

        // pushJob / callback 不应在 kill 路径中被调用
        $service->shouldNotReceive('pushJob');
        $service->shouldNotReceive('callback');

        $service->handle(killJobId: 99);
        $this->addToAssertionCount(1);
    }

    /**
     * killJobId > 0 但 JobRunContent 中没有对应 job：
     * 应调用 sendKillMsg 和 remove，但不调用 kill。
     */
    public function testKillJobWithoutRunningTask(): void
    {
        $service = $this->createService();

        $service->shouldReceive('sendKillMsg')->with(99)->once();
        $service->shouldReceive('remove')->with(99)->once();
        $service->shouldNotReceive('kill');
        $service->shouldNotReceive('pushJob');

        $service->handle(killJobId: 99);
        $this->addToAssertionCount(1);
    }

    /**
     * killJobId > 0 优先级高于 runRequest：
     * 即使传入了 runRequest，也应走 kill 路径。
     */
    public function testKillJobIdTakesPriorityOverRunRequest(): void
    {
        $runRequest = $this->createRunRequest(99);
        JobRunContent::setJobId(99, $runRequest);

        $service = $this->createService();

        $service->shouldReceive('sendKillMsg')->with(99)->once();
        $service->shouldReceive('remove')->with(99)->once();
        $service->shouldReceive('kill')->once();
        $service->shouldNotReceive('pushJob');

        $service->handle($runRequest, 99);
        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    //  Discard Later（丢弃后续调度）测试
    // ──────────────────────────────────────────────

    /**
     * 策略 = DISCARD_LATER，且已有 job 在运行：
     * 应调用 callback(500, 'Discard Later')，不调用 pushJob。
     */
    public function testDiscardLaterWhenJobRunning(): void
    {
        $runRequest = $this->createRunRequest(1, ExecutorBlockStrategyEnum::DISCARD_LATER);
        JobRunContent::setJobId(1, $this->createRunRequest(1));

        $service = $this->createService();

        $service->shouldReceive('callback')
            ->with(
                m::on(fn (RunRequest $r) => $r->getJobId() === 1),
                500,
                'block strategy effect：Discard Later'
            )
            ->once();
        $service->shouldNotReceive('pushJob');

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    /**
     * 策略 = DISCARD_LATER，但没有 job 在运行：
     * 应走默认串行执行路径（pushJob）。
     */
    public function testDiscardLaterWhenNoRunningJob(): void
    {
        $runRequest = $this->createRunRequest(1, ExecutorBlockStrategyEnum::DISCARD_LATER);

        $service = $this->createService();

        $service->shouldNotReceive('callback');
        $service->shouldReceive('pushJob')->with(1, $runRequest)->once();

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    //  Cover Early（覆盖之前调度）测试
    // ──────────────────────────────────────────────

    /**
     * 策略 = COVER_EARLY，Channel push 成功：
     * 应调用 coverEarlyJobLoop，不调用 pushJob。
     */
    public function testCoverEarlySuccess(): void
    {
        $runRequest = $this->createRunRequest(1, ExecutorBlockStrategyEnum::COVER_EARLY);

        $service = $this->createService();

        $service->shouldReceive('coverEarlyJobLoop')
            ->with('coverEarlyJob_1')
            ->once();
        $service->shouldNotReceive('pushJob');
        $service->shouldNotReceive('callback');

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    /**
     * 策略 = COVER_EARLY，Channel 已满导致 push 失败：
     * 应调用 callback(500, '...queue is full...')。
     */
    public function testCoverEarlyChannelFull(): void
    {
        $runRequest = $this->createRunRequest(1, ExecutorBlockStrategyEnum::COVER_EARLY);

        $service = $this->createService();

        // 用真实 Channel 预填充至满（容量 500），模拟队列满场景
        $fullChannel = new Channel(500);
        for ($i = 0; $i < 500; ++$i) {
            $fullChannel->push(RunRequest::create(['jobId' => $i]), 0.1);
        }
        $this->injectChannel($service, 'coverEarlyJob_1', $fullChannel);

        $service->shouldReceive('callback')
            ->with(
                m::on(fn (RunRequest $r) => $r->getJobId() === 1),
                500,
                'CoverEarly job queue is full, please try again later.'
            )
            ->once();
        $service->shouldNotReceive('coverEarlyJobLoop');
        $service->shouldNotReceive('pushJob');

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    //  Serial Execution（串行执行，默认策略）测试
    // ──────────────────────────────────────────────

    /**
     * 默认策略（SERIAL_EXECUTION）：应调用 pushJob。
     */
    public function testSerialExecution(): void
    {
        $runRequest = $this->createRunRequest(1, ExecutorBlockStrategyEnum::SERIAL_EXECUTION);

        $service = $this->createService();

        $service->shouldReceive('pushJob')->with(1, $runRequest)->once();
        $service->shouldNotReceive('callback');
        $service->shouldNotReceive('coverEarlyJobLoop');

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    /**
     * 仅传入 runRequest，killJobId 使用默认值 0：
     * 应走正常 run 路径。
     */
    public function testHandleWithOnlyRunRequest(): void
    {
        $runRequest = $this->createRunRequest(42, ExecutorBlockStrategyEnum::SERIAL_EXECUTION, 999);

        $service = $this->createService();

        $service->shouldReceive('pushJob')->with(42, $runRequest)->once();

        $service->handle($runRequest);
        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    //  边界条件
    // ──────────────────────────────────────────────

    /**
     * killJobId = 0 且无 runRequest（仅理论上可能）：
     * 走 kill 路径的 if 为 false，随后访问 $runRequest 会触发 TypeError。
     * 此处验证 killJobId = 0 时确实不会进入 kill 分支。
     */
    public function testKillJobIdZeroDoesNotEnterKillPath(): void
    {
        $runRequest = $this->createRunRequest(1);

        $service = $this->createService();

        $service->shouldNotReceive('sendKillMsg');
        $service->shouldNotReceive('remove');
        $service->shouldNotReceive('kill');
        $service->shouldReceive('pushJob')->once();

        $service->handle($runRequest, 0);
        $this->addToAssertionCount(1);
    }

    /**
     * 重置 JobRunContent 静态状态，避免测试间污染。
     */
    private function resetJobRunContent(): void
    {
        $ref = new ReflectionClass(JobRunContent::class);
        foreach (['content', 'channels'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, []);
        }
    }

    /**
     * 创建用于测试的 RunRequest。
     */
    private function createRunRequest(
        int $jobId = 1,
        string $strategy = ExecutorBlockStrategyEnum::SERIAL_EXECUTION,
        int $logId = 100,
        int $logDateTime = 20240101120000
    ): RunRequest {
        return RunRequest::create([
            'jobId' => $jobId,
            'logId' => $logId,
            'logDateTime' => $logDateTime,
            'executorBlockStrategy' => $strategy,
            'glueType' => 'BEAN',
            'executorHandler' => 'testHandler',
        ]);
    }

    /**
     * 创建 JobSerialExecutionService 的 partial mock，
     * handle() 真实执行，其他 protected 方法被 mock 以避免副作用。
     *
     * @return JobSerialExecutionService&m\MockInterface
     */
    private function createService(): JobSerialExecutionService
    {
        /** @var JobSerialExecutionService&m\MockInterface $service */
        $service = m::mock(JobSerialExecutionService::class, [
            $this->container,
            $this->glueHandlerManager,
            $this->jobHandlerManager,
            $this->apiRequest,
            $this->config,
        ])->makePartial();

        $service->shouldAllowMockingProtectedMethods();

        // 默认 stub：所有被 mock 的方法如果不在预期内调用则直接放行（不做任何事）
        $service->shouldReceive('sendKillMsg')->byDefault();
        $service->shouldReceive('remove')->byDefault();
        $service->shouldReceive('kill')->byDefault();
        $service->shouldReceive('callback')->byDefault();
        $service->shouldReceive('coverEarlyJobLoop')->byDefault();
        $service->shouldReceive('pushJob')->byDefault();
        $service->shouldReceive('loop')->byDefault();

        return $service;
    }

    /**
     * 向 service 的 channels 数组中注入一个 Channel（用于预设队列状态）。
     */
    private function injectChannel(JobSerialExecutionService $service, string $key, Channel $channel): void
    {
        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);

        // 读取现有 channels 避免覆盖其他测试的注入
        $channels = $prop->getValue($service);
        $channels[$key] = $channel;
        $prop->setValue($service, $channels);
    }
}
