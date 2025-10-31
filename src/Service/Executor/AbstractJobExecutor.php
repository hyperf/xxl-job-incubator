<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Service\Executor;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;

abstract class AbstractJobExecutor implements JobExecutorInterface
{
    public function __construct(
        protected StdoutLoggerInterface $stdoutLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected JobRun $jobRun,
    ) {
    }

    public function isRun(int $jobId): bool
    {
        return JobRunContent::has($jobId);
    }
}
