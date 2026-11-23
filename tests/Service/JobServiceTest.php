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

use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\JobService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\JobService
 */
class JobServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testRejectsUnsupportedGlueTypeBeforeSend(): void
    {
        $glueHandlerManager = m::mock(GlueHandlerManager::class);
        $glueHandlerManager->shouldReceive('supports')->with('GLUE_GROOVY')->andReturnFalse();

        $service = $this->createService($glueHandlerManager);

        try {
            $service->executorBlockStrategy(RunRequest::create([
                'jobId' => 1,
                'logId' => 100,
                'logDateTime' => 20240101120000,
                'executorBlockStrategy' => 'SERIAL_EXECUTION',
                'glueType' => 'GLUE_GROOVY',
                'executorHandler' => 'testHandler',
            ]));
            $this->fail('expected GlueHandlerExecutionException');
        } catch (GlueHandlerExecutionException $exception) {
            $this->assertStringContainsString('GLUE_GROOVY', $exception->getMessage());
        }

        $this->assertSame([], $service->sent);
    }

    public function testScriptJobWithoutExecutorHandlerIsSent(): void
    {
        $glueHandlerManager = m::mock(GlueHandlerManager::class);
        $glueHandlerManager->shouldReceive('supports')->with('GLUE_SHELL')->andReturnTrue();

        $service = $this->createService($glueHandlerManager);
        // 脚本任务通常没有 executorHandler，不应因此触发未初始化属性错误
        $runRequest = RunRequest::create([
            'jobId' => 2,
            'logId' => 101,
            'logDateTime' => 20240101120000,
            'executorBlockStrategy' => 'SERIAL_EXECUTION',
            'glueType' => 'GLUE_SHELL',
        ]);

        $service->executorBlockStrategy($runRequest);

        $this->assertSame([$runRequest], $service->sent);
    }

    /**
     * @param GlueHandlerManager&m\MockInterface $glueHandlerManager
     * @return JobService&object{sent: array}
     */
    private function createService(m\MockInterface $glueHandlerManager): JobService
    {
        return new class(m::mock(ContainerInterface::class), $glueHandlerManager, m::mock(JobHandlerManager::class), m::mock(ApiRequest::class), new Config()) extends JobService {
            public array $sent = [];

            public function send(?RunRequest $runRequest = null, int $killJobId = 0): void
            {
                $this->sent[] = $runRequest;
            }
        };
    }
}
