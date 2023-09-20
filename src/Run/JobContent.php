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
namespace Hyperf\XxlJob\Run;

use Hyperf\XxlJob\Requests\RunRequest;

class JobContent
{
    protected static array $content = [];

    /**
     * @return RunRequest[]
     */
    public function getId($jobId): array
    {
        return self::$content[$jobId] ?? [];
    }

    public function setJobId(int $jobId, int $logId, RunRequest $runRequest): void
    {
        self::$content[$jobId][$logId] = $runRequest;
    }

    public function remove(int $jobId, int $logId): void
    {
        unset(self::$content[$jobId][$logId]);
    }
}
