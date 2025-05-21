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

namespace Hyperf\XxlJob\Service;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Channel;
use Hyperf\XxlJob\Locker;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;

class JobSerialExecutionService extends BaseService
{
    protected array $channels = [];

    protected array $mark = [];

    public function handle(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        if ($killJobId > 0) {
            $this->sendKillMsg($killJobId);
            $this->remove($killJobId);
            return;
        }
        $jobId = $runRequest->getJobId();
        if ($runRequest->isCoverEarly()) {
            $this->coverEarlyJob($jobId, $runRequest);
            return;
        }
        $this->channels[$jobId] ??= new Channel(1000);
        $this->channels[$jobId]->push($runRequest, 5);
        $this->loop($jobId);
    }

    protected function coverEarlyJob(int $jobId, RunRequest $runRequest): void
    {
        $key = 'coverEarlyJob_' . $jobId;
        Locker::lock($key);
        try {
            if (JobRunContent::has($jobId)) {
                $this->kill($jobId, 0, 'block strategy effect：Cover Early [job running, killed]');
                // waiting for the process to completely end
                sleep(1);
            }
            $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
        } finally {
            Locker::unlock($key);
        }
    }

    protected function loop(int $jobId): void
    {
        $mark = $this->mark[$jobId] ?? false;
        if ($mark) {
            return;
        }
        $this->mark[$jobId] = true;

        Coroutine::create(function () use ($jobId) {
            try {
                while (true) {
                    $channels = $this->channels[$jobId] ?? null;
                    $runRequest = $channels?->pop(-1);
                    if ($runRequest instanceof RunRequest) {
                        $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                        JobRunContent::yield($runRequest->getLogId());
                    } else {
                        return;
                    }
                }
            } finally {
                $this->remove($jobId);
            }
        });
    }

    protected function remove(int $jobId): void
    {
        unset($this->channels[$jobId], $this->mark[$jobId]);
    }

    private function sendKillMsg(int $jobId): void
    {
        $data = [];
        $channel = $this->channels[$jobId] ?? null;
        while ($channel && ! $channel->isEmpty()) {
            /** @var RunRequest $runRequest */
            $runRequest = $this->channels[$jobId]->pop(5);
            $tmp['logId'] = $runRequest->getLogId();
            $tmp['logDateTim'] = $runRequest->getLogDateTime();
            $data[] = $tmp;
        }
        $this->apiRequest->multipleCallback($data, 500, 'scheduling center kill job. [job not executed, in the job queue, killed.]');
    }
}
