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

use Hyperf\XxlJob\Exception\XxlJobException;
use Throwable;

class JobPipeFileStore
{
    public function __construct(protected Config $config)
    {
    }

    public function putPayload(string $payload): string
    {
        $directory = $this->prepareDirectory();
        $path = tempnam($directory, 'payload_');
        if ($path === false) {
            throw new XxlJobException('Failed to create XXL-JOB pipe payload file.');
        }
        try {
            if (file_put_contents($path, $payload) !== strlen($payload)) {
                throw new XxlJobException('Failed to write XXL-JOB pipe payload file.');
            }
            return $path;
        } catch (Throwable $throwable) {
            if (is_file($path)) {
                @unlink($path);
            }
            throw $throwable;
        }
    }

    public function publishReply(string $replyId, int $code, string $message = ''): void
    {
        $this->assertReplyId($replyId);
        $directory = $this->prepareDirectory();
        $path = $this->getReplyPath($replyId);
        $json = json_encode(['code' => $code, 'msg' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new XxlJobException('Failed to encode XXL-JOB kill reply.');
        }
        // 先完整写临时文件再 rename，等待方永远不会读到半截 ACK
        $temporary = tempnam($directory, 'reply_tmp_');
        if ($temporary === false) {
            throw new XxlJobException('Failed to create XXL-JOB kill reply file.');
        }
        try {
            if (file_put_contents($temporary, $json) !== strlen($json) || ! @rename($temporary, $path)) {
                throw new XxlJobException('Failed to publish XXL-JOB kill reply.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function consumeReply(string $replyId): ?array
    {
        $this->assertReplyId($replyId);
        $path = $this->getReplyPath($replyId);
        if (! is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return is_file($path) ? throw new XxlJobException('Failed to read XXL-JOB kill reply.') : null;
        }
        @unlink($path);
        $reply = json_decode($contents, true);
        if (! is_array($reply) || ! is_int($reply['code'] ?? null)) {
            throw new XxlJobException('Invalid XXL-JOB kill reply.');
        }
        return $reply;
    }

    public function cleanup(int $maxAgeSeconds = 300): void
    {
        $directory = $this->getDirectory();
        if (! is_dir($directory)) {
            return;
        }
        $deadline = time() - $maxAgeSeconds;
        foreach (array_merge(
            glob($directory . '/payload_*') ?: [],
            glob($directory . '/reply_*') ?: [],
            glob(sys_get_temp_dir() . '/xxl_job_pipe_*') ?: [],
        ) as $path) {
            // 只清理超过宽限期的文件，避免 dispatcher 重启时误删正在投递的新消息
            if (is_file($path) && filemtime($path) < $deadline) {
                @unlink($path);
            }
        }
    }

    public function getDirectory(): string
    {
        // 同一台机器可能运行多个执行器，目录按应用名与日志目录隔离，启动清理不能误删其他实例消息
        return sys_get_temp_dir() . '/xxl_job_pipe_' . md5($this->config->getAppName() . '|' . $this->config->getLogFileDir());
    }

    protected function prepareDirectory(): string
    {
        $directory = $this->getDirectory();
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB pipe directory: %s.', $directory));
        }
        return $directory;
    }

    protected function getReplyPath(string $replyId): string
    {
        return $this->getDirectory() . '/reply_' . $replyId;
    }

    protected function assertReplyId(string $replyId): void
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $replyId)) {
            throw new XxlJobException('Invalid XXL-JOB kill reply id.');
        }
    }
}
