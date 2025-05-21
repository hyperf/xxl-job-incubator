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

namespace Hyperf\XxlJob\Service;

use Hyperf\Engine\Constant;
use Hyperf\Process\ProcessCollector;
use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Process\JobDispatcherProcess;
use Hyperf\XxlJob\Requests\RunRequest;
use Swoole\Server;

class JobService extends BaseService
{
    public function send(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        $jobSerialExecutionService = $this->container->get(JobSerialExecutionService::class);
        if (Constant::ENGINE == 'Swoole') {
            $server = $this->container->get(Server::class);
            if ($server instanceof Server) {
                $jobPipeMessage = new JobPipeMessage($runRequest, $killJobId, $server->worker_id);
                $process = ProcessCollector::get(JobDispatcherProcess::JOB_DISPATCHER_NAME)[0] ?? null;
                $exportSocket = $process->exportSocket();
                $exportSocket->send(serialize($jobPipeMessage), 10);
                return;
            }
        }
        $jobSerialExecutionService->handle($runRequest, $killJobId);
    }

    public function executorBlockStrategy(RunRequest $runRequest): void
    {
        $executorHandler = $runRequest->getExecutorHandler();
        $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);

        if ($runRequest->getGlueType() == GlueEnum::BEAN && (empty($jobDefinition) || ! method_exists($jobDefinition->getClass(), $jobDefinition->getMethod()))) {
            throw new GlueHandlerExecutionException(sprintf('The definition of executor handler %s is invalid.', $executorHandler));
        }

        $jobKillExecutor = $this->getJobExecutor($runRequest->getJobId());

        switch ($runRequest->getExecutorBlockStrategy()) {
            case ExecutorBlockStrategyEnum::SERIAL_EXECUTION:
            case ExecutorBlockStrategyEnum::COVER_EARLY:
                $this->send($runRequest);
                return;
            case ExecutorBlockStrategyEnum::DISCARD_LATER:
                $isRun = $jobKillExecutor->isRun($runRequest->getJobId());
                if ($isRun) {
                    throw new XxlJobException('block strategy effect：Discard Later');
                }
                $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                break;
        }
    }
}
