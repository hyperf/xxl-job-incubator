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
use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\JobContent;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Requests\RunRequest;
use Swoole\Server;

class JobService extends BaseService
{
    public function send(JobPipeMessage $jobPipeMessage): void
    {
        $jobSerialExecutionService = $this->container->get(JobSerialExecutionService::class);
        if (Constant::ENGINE == 'Swoole') {
            $server = $this->container->get(Server::class);
            if ($server->worker_id != 0) {
                $server->sendMessage($jobPipeMessage, 0);
                return;
            }
            $jobSerialExecutionService->handle($jobPipeMessage);
        } else {
            $jobSerialExecutionService->handle($jobPipeMessage);
        }
    }

    public function executorBlockStrategy(RunRequest $runRequest): void
    {
        $executorHandler = $runRequest->getExecutorHandler();
        $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);

        if (empty($jobDefinition) || ! method_exists($jobDefinition->getClass(), $jobDefinition->getMethod())) {
            throw new GlueHandlerExecutionException(sprintf('The definition of executor handler %s is invalid.', $executorHandler));
        }

        $jobKillExecutor = $this->getKillExecutor();
        $isRun = $jobKillExecutor->isRun($runRequest->getJobId());

        switch ($runRequest->getExecutorBlockStrategy()) {
            case ExecutorBlockStrategyEnum::SERIAL_EXECUTION:
                $this->send(new JobPipeMessage($runRequest));
                return;
            case ExecutorBlockStrategyEnum::DISCARD_LATER:
                if ($isRun) {
                    throw new XxlJobException('block strategy effect：Discard Later');
                }
                break;
            case ExecutorBlockStrategyEnum::COVER_EARLY:
                if ($isRun) {
                    $this->kill($runRequest->getJobId(), 0, 'block strategy effect：Cover Early [job running, killed]');
                }
                break;
        }
        JobContent::setJobId($runRequest->getJobId(), $runRequest);
        $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
    }
}
