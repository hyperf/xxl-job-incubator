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

namespace Hyperf\XxlJob;

use Hyperf\XxlJob\Requests\RunRequest;

class JobPipeMessage
{
    public function __construct(
        public ?RunRequest $runRequest = null,
        public int $killJobId = 0,
        public int $fromWorkerId = -1,
        public ?string $payloadFile = null,
        public ?string $replyId = null,
        public int $logId = 0,
        public int $logDateTime = 0,
    ) {
        if ($runRequest) {
            $this->logId = $runRequest->getLogId();
            $this->logDateTime = $runRequest->getLogDateTime();
        }
    }

    public function resolve(): void
    {
        if ($this->payloadFile === null) {
            return;
        }

        $payloadFile = $this->payloadFile;
        $this->payloadFile = null;
        try {
            $payload = @file_get_contents($payloadFile);
            if ($payload === false) {
                throw new Exception\XxlJobException('Failed to read XXL-JOB pipe payload file.');
            }
            $message = @unserialize($payload, ['allowed_classes' => [self::class, RunRequest::class]]);
            // 只允许一层文件转发，避免异常消息形成不可控的间接读取链
            if (! $message instanceof self || $message->payloadFile !== null) {
                throw new Exception\XxlJobException('Invalid XXL-JOB pipe payload file.');
            }
            $this->runRequest = $message->runRequest;
            $this->killJobId = $message->killJobId;
            $this->fromWorkerId = $message->fromWorkerId;
            $this->replyId = $message->replyId;
            $this->logId = $message->logId;
            $this->logDateTime = $message->logDateTime;
        } finally {
            // 无论解析成功与否都只消费一次，避免 dispatcher 重启后重复执行旧任务
            @unlink($payloadFile);
        }
    }
}
