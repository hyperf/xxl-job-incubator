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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Glue\Handlers\ScriptHandler;
use Hyperf\XxlJob\Handler\JobHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Glue\Handlers\ScriptHandler
 */
class ScriptHandlerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xxl_job_script_' . uniqid('', true);
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        m::close();
        foreach (glob($this->directory . '/glue_scripts/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory . '/glue_scripts')) {
            rmdir($this->directory . '/glue_scripts');
        }
        rmdir($this->directory);
    }

    public function testConcurrentGlueTypesDoNotPolluteEachOther(): void
    {
        $config = new Config();
        $config->setLogFileDir($this->directory . '/logs/');
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Config::class)->andReturn($config);
        $callbacks = [];
        $executorCoroutine = m::mock(JobExecutorCoroutine::class);
        $executorCoroutine->shouldReceive('executeCoroutine')
            ->twice()
            ->andReturnUsing(static function (RunRequest $request, callable $callback) use (&$callbacks): int {
                $callbacks[$request->getLogId()] = $callback;
                return 1;
            });
        $handler = new class($container, m::mock(JobHandlerManager::class), m::mock(JobExecutorLoggerInterface::class), m::mock(StdoutLoggerInterface::class), m::mock(JobExecutorProcess::class), $executorCoroutine) extends ScriptHandler {
            public array $commands = [];

            protected function executeCmd(string $filePath, RunRequest $request): void
            {
                $this->commands[] = [$filePath, $request->getGlueType()];
            }
        };
        $shell = $this->createRunRequest(1, 101, 'GLUE_SHELL');
        $python = $this->createRunRequest(2, 102, 'GLUE_PYTHON');

        // 先让两个 handle 共用同一实例，再执行第一个闭包，稳定复现旧实现的属性覆盖
        $handler->handle($shell);
        $handler->handle($python);
        $callbacks[101]($shell);
        $callbacks[102]($python);

        self::assertCount(2, $handler->commands);
        self::assertSame('GLUE_SHELL', $handler->commands[0][1]);
        self::assertTrue(str_ends_with($handler->commands[0][0], '1-1000.sh'));
        self::assertSame('GLUE_PYTHON', $handler->commands[1][1]);
        self::assertTrue(str_ends_with($handler->commands[1][0], '2-1000.py'));
    }

    private function createRunRequest(int $jobId, int $logId, string $glueType): RunRequest
    {
        return RunRequest::create([
            'jobId' => $jobId,
            'logId' => $logId,
            'logDateTime' => 123456,
            'glueType' => $glueType,
            'glueSource' => 'echo test',
            'glueUpdatetime' => 1000,
        ]);
    }
}
