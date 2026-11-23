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

namespace Hyperf\XxlJob\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Process\Event\PipeMessage as UserProcessPipeMessage;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\JobPipeFileStore;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Throwable;

class OnPipeMessageListener implements ListenerInterface
{
    public function __construct(
        protected JobSerialExecutionService $jobSerialExecutionService,
        protected ConfigInterface $config,
        protected StdoutLoggerInterface $logger,
        protected JobPipeFileStore $pipeFileStore,
        protected ApiRequest $apiRequest,
    ) {
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            UserProcessPipeMessage::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event): void
    {
        if ($event instanceof UserProcessPipeMessage) {
            if ($event->data instanceof JobPipeMessage) {
                Coroutine::create(function () use ($event) {
                    try {
                        // 大消息在发送端落盘，这里恢复原始请求并立即清理临时文件
                        $event->data->resolve();
                        $this->jobSerialExecutionService->handle($event->data->runRequest, $event->data->killJobId);
                        if ($event->data->replyId !== null) {
                            $this->pipeFileStore->publishReply($event->data->replyId, 200);
                        }
                    } catch (Throwable $throwable) {
                        $this->logger->error($throwable->getMessage());
                        if ($event->data->replyId !== null) {
                            // kill 请求携带 replyId，HTTP worker 必须拿到真实失败而不是继续回 200
                            $this->pipeFileStore->publishReply($event->data->replyId, 500, $throwable->getMessage());
                        } elseif ($event->data->logId > 0) {
                            try {
                                // 大消息信封单独携带 logId，即使文件损坏也能告知 admin，而不是永久 running
                                $this->apiRequest->callback($event->data->logId, $event->data->logDateTime, 500, $throwable->getMessage());
                            } catch (Throwable $callbackError) {
                                $this->logger->error($callbackError->getMessage());
                            }
                        } else {
                            throw $throwable;
                        }
                    }
                });
            }
        }
    }
}
