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

use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Constant;
use Hyperf\Process\ProcessCollector;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Process\JobDispatcherProcess;
use Hyperf\XxlJob\Process\JobPipeFileStore;
use Hyperf\XxlJob\Process\JobPipeMessage;
use Hyperf\XxlJob\Requests\RunRequest;
use Swoole\Server;
use Throwable;

class JobService extends BaseService
{
    private ?JobPipeFileStore $pipeFileStore = null;

    public function send(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        $jobSerialExecutionService = $this->container->get(JobSerialExecutionService::class);
        // Constant::ENGINE 在经典异步与协程风格服务下都是 'Swoole'，不能用来判断进程模型；
        // 只有经典异步服务返回 Swoole\Server，才有独立的 dispatcher 进程与 pipe
        $server = Constant::ENGINE == 'Swoole' ? $this->container->get(Server::class) : null;
        if ($server instanceof Server && ! Constant::isCoroutineServer($server)) {
            $replyId = $killJobId > 0 ? bin2hex(random_bytes(16)) : null;
            $jobPipeMessage = new JobPipeMessage($runRequest, $killJobId, $server->worker_id, replyId: $replyId);
            $this->sendToDispatcher($jobPipeMessage);
            if ($replyId !== null) {
                $this->waitForKillReply($replyId);
            }
            return;
        }
        $jobSerialExecutionService->handle($runRequest, $killJobId);
    }

    public function executorBlockStrategy(RunRequest $runRequest): void
    {
        $glueType = $runRequest->getGlueType();
        // 不支持的 glueType 在这里就拒绝：否则 /trigger 已回 200，dispatcher 里才抛异常且 admin 收不到回调
        if (! $this->glueHandlerManager->supports($glueType)) {
            throw new GlueHandlerExecutionException(sprintf('The glue type %s is invalid or does not support yet.', $glueType));
        }

        // 3.5.0 新增执行器级 GLUE 开关；与官方一致，BEAN 始终允许，非 BEAN 统一拒绝
        if ($glueType !== GlueEnum::BEAN && ! $this->config->isGlueEnabled()) {
            throw new GlueHandlerExecutionException(sprintf(
                'The glue type %s is disabled, please set xxl_job.glue_enabled=true to enable it.',
                $glueType
            ));
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

    protected function waitForKillReply(string $replyId): void
    {
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $reply = $this->pipeFileStore()->consumeReply($replyId);
            if ($reply !== null) {
                if ($reply['code'] !== 200) {
                    throw new GlueHandlerExecutionException($reply['msg'] !== '' ? $reply['msg'] : 'XXL-JOB kill failed.');
                }
                return;
            }
            Coroutine::sleep(0.01);
        }
        throw new GlueHandlerExecutionException('Timed out waiting for XXL-JOB kill acknowledgement.');
    }

    protected function serializePipeMessage(JobPipeMessage $jobPipeMessage, ?string &$payloadFile): string
    {
        $payload = serialize($jobPipeMessage);
        if (strlen($payload) <= JobDispatcherProcess::RECV_LENGTH) {
            return $payload;
        }

        $path = $this->pipeFileStore()->putPayload($payload);
        $payloadFile = $path;

        return serialize(new JobPipeMessage(
            killJobId: $jobPipeMessage->killJobId,
            fromWorkerId: $jobPipeMessage->fromWorkerId,
            payloadFile: $path,
            replyId: $jobPipeMessage->replyId,
            logId: $jobPipeMessage->logId,
            logDateTime: $jobPipeMessage->logDateTime,
        ));
    }

    protected function pipeFileStore(): JobPipeFileStore
    {
        return $this->pipeFileStore ??= new JobPipeFileStore($this->config);
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
