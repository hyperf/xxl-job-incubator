<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Kill;
class JobKillContent
{
    protected static array $content = [];

    public function setJobId($jobId, int $cid): void
    {
        static::$content[$jobId] = $cid;
    }

    public function getCid($jobId): ?int
    {
        return static::$content[$jobId] ?? null;
    }

    public function unsetJobId($jobId): void
    {
        unset(static::$content[$jobId]);
    }
}
