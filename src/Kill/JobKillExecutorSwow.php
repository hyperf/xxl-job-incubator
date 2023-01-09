<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Kill;

use Hyperf\Contract\StdoutLoggerInterface;
use Swow\Coroutine;

class JobKillExecutorSwow implements JobKillExecutorInterface
{
    public function __construct(
        protected JobKillContent $jobKillContent,
        protected StdoutLoggerInterface $stdoutLogger
    ) {
    }

    public function kill(int $jobId): void
    {
        $cid = $this->jobKillContent->getCid($jobId);
        if (empty($cid)) {
            $this->stdoutLogger->info("The cid for obtaining the jobId:$jobId is empty. The job may have ended");
            return;
        }
        Coroutine::get($cid)?->kill();
    }
}
