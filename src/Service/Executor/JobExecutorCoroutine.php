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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Engine\Constant;
use Hyperf\Engine\Coroutine;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Swow\Coroutine as SwowCoroutine;

class JobExecutorCoroutine implements JobExecutorInterface
{
    public function __construct(
        protected StdoutLoggerInterface $stdoutLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected JobRun $jobRun,
        protected ChannelFactory $channelFactory,
    ) {
    }

    public function isRun(int $jobId): bool
    {
        return JobRunContent::has($jobId);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $runRequest = JobRunContent::getId($jobId);
        if (empty($runRequest)) {
            return true;
        }

        if (Constant::ENGINE == 'Swoole') {
            $this->stdoutLogger->warning('Swoole coroutine mode does not support kill tasks');
            return false;
        }

        SwowCoroutine::get($runRequest->getId())?->kill();
        JobRunContent::remove($jobId);
        if ($msg) {
            $this->jobExecutorLogger->warning($msg);
            $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $msg);
        }
        return true;
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        // executorTimeout
        $executorTimeout = $request->getExecutorTimeout();
        if ($executorTimeout > 0) {
            Coroutine::create(function () use ($request) {
                $result = $this->channelFactory->pop($request->getLogId(), $request->getExecutorTimeout());
                if ($result === false) {
                    $this->kill($request->getJobId(), $request->getLogId(), 'scheduling center kill job. [job running, killed]');
                }
            });
        }

        $this->jobRun->executeCoroutine($request, $callback);
    }
}
