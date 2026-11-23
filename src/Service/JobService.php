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
use Throwable;

class JobService extends BaseService
{
    public function send(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        $jobSerialExecutionService = $this->container->get(JobSerialExecutionService::class);
        if (Constant::ENGINE == 'Swoole') {
            $server = $this->container->get(Server::class);
            if ($server instanceof Server) {
                $jobPipeMessage = new JobPipeMessage($runRequest, $killJobId, $server->worker_id);
                $this->sendToDispatcher($jobPipeMessage);
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

    /**
     * 小消息直接走 pipe；超过接收上限的 GLUE 源码先完整落盘，避免 datagram 静默截断。
     */
    protected function sendToDispatcher(JobPipeMessage $jobPipeMessage): void
    {
        $payloadFile = null;
        try {
            $process = ProcessCollector::get(JobDispatcherProcess::JOB_DISPATCHER_NAME)[0] ?? null;
            if (! $process) {
                throw new GlueHandlerExecutionException('XXL-JOB dispatcher process is unavailable.');
            }
            $exportSocket = $process->exportSocket();
            if ($exportSocket === false) {
                throw new GlueHandlerExecutionException('XXL-JOB dispatcher socket is unavailable.');
            }

            $payload = $this->serializePipeMessage($jobPipeMessage, $payloadFile);
            $sent = $exportSocket->send($payload, 10);
            if ($sent === false || $sent !== strlen($payload)) {
                throw new GlueHandlerExecutionException('XXL-JOB dispatcher pipe send failed or wrote a partial message.');
            }
            // 大消息发送成功后，临时文件交由接收端 resolve() 消费并删除
        } catch (Throwable $throwable) {
            if ($payloadFile !== null) {
                @unlink($payloadFile);
            }
            $this->callbackDeliveryFailure($jobPipeMessage->runRequest, $throwable->getMessage());
            if ($throwable instanceof GlueHandlerExecutionException) {
                throw $throwable;
            }
            throw new GlueHandlerExecutionException($throwable->getMessage(), 0, $throwable);
        }
    }

    protected function serializePipeMessage(JobPipeMessage $jobPipeMessage, ?string &$payloadFile): string
    {
        $payload = serialize($jobPipeMessage);
        if (strlen($payload) <= JobDispatcherProcess::RECV_LENGTH) {
            return $payload;
        }

        $path = tempnam(sys_get_temp_dir(), 'xxl_job_pipe_');
        if ($path === false) {
            throw new GlueHandlerExecutionException('Failed to create XXL-JOB pipe payload file.');
        }
        $payloadFile = $path;
        // tempnam 已独占创建文件，且完整写入前不会把路径发给接收端，因此无需再 rename
        if (file_put_contents($path, $payload) !== strlen($payload)) {
            throw new GlueHandlerExecutionException('Failed to write XXL-JOB pipe payload file.');
        }

        return serialize(new JobPipeMessage(
            killJobId: $jobPipeMessage->killJobId,
            fromWorkerId: $jobPipeMessage->fromWorkerId,
            payloadFile: $path,
        ));
    }

    protected function callbackDeliveryFailure(?RunRequest $runRequest, string $message): void
    {
        if (! $runRequest) {
            return;
        }
        try {
            // 投递失败必须同时通过 HTTP 与 callback 告知 admin，避免日志永久停在 running
            $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $message);
        } catch (Throwable) {
            // admin 不可达时保留原始投递异常，不能用回调异常覆盖真正的失败原因
        }
    }
}
