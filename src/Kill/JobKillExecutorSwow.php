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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Run\JobContent;
use Swow\Coroutine;

class JobKillExecutorSwow extends JobContent implements JobKillExecutorInterface
{
    public function __construct(
        protected StdoutLoggerInterface $stdoutLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
    ) {}

    public function isRun(int $jobId): bool
    {
        return isset(self::$content[$jobId]);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $runRequests = $this->getId($jobId);
        if ($logId > 0) {
            if (! isset($runRequests[$logId])) {
                return false;
            }
            $runRequests = [$runRequests[$logId]];
        }

        foreach ($runRequests as $runRequest) {
            Coroutine::get($runRequest->getId())?->kill();
            $this->remove($jobId, $runRequest->getLogId());
            if ($msg) {
                $this->jobExecutorLogger->info($msg);
                $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $msg);
            }
        }
        return true;
    }
}
