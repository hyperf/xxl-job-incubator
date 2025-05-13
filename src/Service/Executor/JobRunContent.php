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

use Hyperf\XxlJob\Requests\RunRequest;

class JobRunContent
{
    /**
     * @var RunRequest[]
     */
    protected static array $content = [];

    public static function getId(int $jobId): ?RunRequest
    {
        return self::$content[$jobId] ?? null;
    }

    public static function getAll(): array
    {
        return self::$content;
    }

    public static function has(int $jobId): bool
    {
        return isset(self::$content[$jobId]);
    }

    public static function setJobId(int $jobId, RunRequest $runRequest): void
    {
        self::$content[$jobId] = $runRequest;
    }

    public static function remove(int $jobId): void
    {
        unset(self::$content[$jobId]);
    }
}
