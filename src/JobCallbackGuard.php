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

use Hyperf\Coroutine\Coroutine;
use Hyperf\XxlJob\Exception\XxlJobException;
use Psr\Http\Message\ResponseInterface;

class JobCallbackGuard
{
    public function __construct(protected Config $config)
    {
    }

    public function execute(int $logId, callable $callback): ?ResponseInterface
    {
        $directory = $this->config->getLogFileDir();
        if ($directory === '') {
            // 未配置文件日志目录时保留原有直发行为，避免把标记写到进程工作目录
            return $callback();
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB callback guard directory: %s.', $directory));
        }

        $path = $directory . sprintf('logId_%s.callback', $logId);
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            throw new XxlJobException(sprintf('Failed to open XXL-JOB callback guard: %s.', $path));
        }
        try {
            $deadline = microtime(true) + 15;
            while (! flock($handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new XxlJobException(sprintf('Timed out locking XXL-JOB callback guard: %s.', $path));
                }
                // 非阻塞锁加协程睡眠，避免父子进程竞争时把整个 worker 阻塞在 flock 上
                Coroutine::sleep(0.01);
            }
            try {
                $status = stream_get_contents($handle);
                if ($status === false) {
                    throw new XxlJobException(sprintf('Failed to read XXL-JOB callback guard: %s.', $path));
                }
                if ($status === 'done') {
                    return null;
                }

                // 锁覆盖整个请求：另一进程只有等本次结果确定后，才能决定跳过或重试
                $response = $callback();
                // 只有 admin 请求成功才落完成标记；异常路径由 finally 解锁，后续调用仍可重试
                if (! ftruncate($handle, 0) || ! rewind($handle) || fwrite($handle, 'done') !== 4) {
                    throw new XxlJobException(sprintf('Failed to mark XXL-JOB callback completed: %s.', $path));
                }
                if (! fflush($handle)) {
                    throw new XxlJobException(sprintf('Failed to flush XXL-JOB callback guard: %s.', $path));
                }
                return $response;
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
