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

namespace HyperfTest\XxlJob;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Container;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\Handlers\BeanCommandHandler;
use Hyperf\XxlJob\JobCommand;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\JobCommand
 */
class JobCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testJobIdRejectsDifferentLogId(): void
    {
        $handler = m::mock(BeanCommandHandler::class);
        $handler->shouldNotReceive('handle');
        $process = m::mock(JobExecutorProcess::class);
        $process->shouldReceive('getJobFileInfo')->once()->with(1)->andReturn([
            'logId' => 200,
            'runRequest' => RunRequest::create(['jobId' => 1, 'logId' => 200]),
        ]);
        $command = $this->createCommand($handler, $process, 100);

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('belongs to another logId');
        $command->handle();
    }

    public function testJobIdExecutesMatchingLogId(): void
    {
        $request = RunRequest::create(['jobId' => 1, 'logId' => 100]);
        $handler = m::mock(BeanCommandHandler::class);
        $handler->shouldReceive('handle')->once()->with($request);
        $process = m::mock(JobExecutorProcess::class);
        $process->shouldReceive('getJobFileInfo')->once()->with(1)->andReturn([
            'logId' => 100,
            'runRequest' => $request,
        ]);
        $command = $this->createCommand($handler, $process, 100);

        $command->handle();
        $this->addToAssertionCount(1);
    }

    private function createCommand(BeanCommandHandler $handler, JobExecutorProcess $process, int $logId): JobCommand
    {
        $command = new JobCommand($handler, $process, m::mock(Container::class));
        $input = m::mock(InputInterface::class);
        $input->shouldReceive('getOptions')->once()->andReturn([
            'jobId' => 1,
            'logId' => $logId,
            'handler' => null,
            'params' => null,
        ]);
        $ref = new ReflectionClass(HyperfCommand::class);
        $property = $ref->getProperty('input');
        $property->setAccessible(true);
        $property->setValue($command, $input);
        return $command;
    }
}
