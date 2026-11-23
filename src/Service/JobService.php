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
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
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
        $glueType = $runRequest->getGlueType();
        // 不支持的 glueType 在这里就拒绝：否则 /run 已回 200，dispatcher 里才抛异常且 admin 收不到回调
        if (! $this->glueHandlerManager->supports($glueType)) {
            throw new GlueHandlerExecutionException(sprintf('The glue type %s is invalid or does not support yet.', $glueType));
        }

        if ($glueType == GlueEnum::BEAN) {
            $executorHandler = $runRequest->getExecutorHandler();
            $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);
            if (empty($jobDefinition) || ! method_exists($jobDefinition->getClass(), $jobDefinition->getMethod())) {
                throw new GlueHandlerExecutionException(sprintf('The definition of executor handler %s is invalid.', $executorHandler));
            }
        }
        $this->send($runRequest);
    }
}
