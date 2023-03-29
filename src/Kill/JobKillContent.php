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
namespace Hyperf\XxlJob\Kill;

class JobKillContent
{
    protected static array $content = [];

    public function setJobId($jobId, int $cid): void
    {
        self::$content[$jobId] = $cid;
    }

    public function getCid($jobId): ?int
    {
        return self::$content[$jobId] ?? null;
    }

    public function unsetJobId($jobId): void
    {
        unset(self::$content[$jobId]);
    }
}
