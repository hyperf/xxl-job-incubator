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

namespace Hyperf\XxlJob\Service\Executor;

use Hyperf\Coordinator\Coordinator;
use Hyperf\XxlJob\Requests\RunRequest;

class JobRunContent
{
    /**
     * @var RunRequest[]
     */
    protected static array $content = [];

    protected static array $channels = [];

    public static function getId(int $jobId): ?RunRequest
    {
        return self::$content[$jobId] ?? null;
    }

    public static function has(int $jobId): bool
    {
        return isset(self::$content[$jobId]);
    }

    /**
     * Get all currently running job IDs.
     */
    public static function getRunningJobIds(): array
    {
        return array_keys(self::$content);
    }

    public static function setJobId(int $jobId, RunRequest $runRequest): void
    {
        self::$content[$jobId] = $runRequest;
    }

    public static function remove(int $jobId, int $logId = 0): void
    {
        $channel = static::getCoordinator($logId);
        unset(self::$channels[$logId]);
        // 旧运行收尾只清理同 logId 的登记，避免删掉同 jobId 已开始的新一代运行
        if (isset(self::$content[$jobId]) && self::$content[$jobId]->getLogId() === $logId) {
            unset(self::$content[$jobId]);
        }
        // 即使登记已换代，也要唤醒仍在等待当前 logId 的协程
        $channel->resume();
    }

    public static function yield(int $logId, int $timeout = -1): bool
    {
        if (! isset(self::$channels[$logId]) && ! static::hasRunningLogId($logId)) {
            // 任务可能在调用 yield 前已完成并删除 Coordinator，此时应直接返回完成而不是新建后空等
            return true;
        }
        return static::getCoordinator($logId)->yield($timeout);
    }

    private static function hasRunningLogId(int $logId): bool
    {
        foreach (self::$content as $runRequest) {
            if ($runRequest->getLogId() === $logId) {
                return true;
            }
        }
        return false;
    }

    private static function getCoordinator(int $logId): Coordinator
    {
        if (! isset(static::$channels[$logId])) {
            static::$channels[$logId] = new Coordinator();
        }

        return static::$channels[$logId];
    }
}
