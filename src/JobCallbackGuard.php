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
use Throwable;

class JobCallbackGuard
{
    public function __construct(protected Config $config)
    {
    }

    public function execute(int $logId, callable $callback): ?ResponseInterface
    {
        return $this->executeMany([$logId], static function (array $pendingLogIds) use ($callback): ResponseInterface {
            return $callback();
        });
    }

    public function executeMany(array $logIds, callable $callback): ?ResponseInterface
    {
        $orderedIds = [];
        foreach ($logIds as $logId) {
            $logId = (int) $logId;
            if (! in_array($logId, $orderedIds, true)) {
                $orderedIds[] = $logId;
            }
        }
        $directory = $this->config->getLogFileDir();
        if ($directory === '') {
            // 未配置文件日志目录时保留原有直发行为，避免把标记写到进程工作目录
            return $callback($orderedIds);
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB callback guard directory: %s.', $directory));
        }

        $lockIds = $orderedIds;
        sort($lockIds, SORT_NUMERIC);
        $handles = [];
        try {
            // 多回调按固定顺序加锁，避免不同批次以相反顺序持锁形成死锁
            foreach ($lockIds as $logId) {
                $handles[$logId] = $this->acquire($directory, $logId);
            }
            $pendingIds = [];
            foreach ($orderedIds as $logId) {
                if (! $this->isCompleted($handles[$logId])) {
                    $pendingIds[] = $logId;
                }
            }
            if ($pendingIds === []) {
                return null;
            }

            // 锁覆盖整个请求：其他进程只有等结果确定后，才能决定跳过或重试
            $response = $callback($pendingIds);
            foreach ($pendingIds as $logId) {
                $this->markCompleted($handles[$logId], $logId);
            }
            return $response;
        } finally {
            foreach ($handles as $handle) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    protected function acquire(string $directory, int $logId)
    {
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
            return $handle;
        } catch (Throwable $throwable) {
            fclose($handle);
            throw $throwable;
        }
    }

    protected function isCompleted($handle): bool
    {
        $status = stream_get_contents($handle);
        if ($status === false) {
            throw new XxlJobException('Failed to read XXL-JOB callback guard.');
        }
        return $status === 'done';
    }

    protected function markCompleted($handle, int $logId): void
    {
        // 只有 admin 请求成功才落完成标记；异常路径由 finally 解锁，后续调用仍可重试
        if (! ftruncate($handle, 0) || ! rewind($handle) || fwrite($handle, 'done') !== 4 || ! fflush($handle)) {
            throw new XxlJobException(sprintf('Failed to mark XXL-JOB callback completed for LogId:%s.', $logId));
        }
    }
}
