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

    public static function setJobId(int $jobId, RunRequest $runRequest): void
    {
        self::$content[$jobId] = $runRequest;
    }

    public static function remove(int $jobId, int $logId = 0): void
    {
        $channel = static::getCoordinator($logId);
        unset(self::$channels[$logId], self::$content[$jobId]);
        $channel->resume();
        // var_dump(date('Y-m-d H:i:s') . '  remove.........');
    }

    public static function yield(int $logId, int $timeout = -1): bool
    {
        return static::getCoordinator($logId)->yield($timeout);
    }

    private static function getCoordinator(int $logId): Coordinator
    {
        if (! isset(static::$channels[$logId])) {
            static::$channels[$logId] = new Coordinator();
        }

        return static::$channels[$logId];
    }
}
