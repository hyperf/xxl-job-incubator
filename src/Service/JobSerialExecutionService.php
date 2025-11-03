<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Service;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Channel;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;

class JobSerialExecutionService extends BaseService
{
    protected array $channels = [];

    protected array $mark = [];

    public function handle(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        // kill job
        if ($killJobId > 0) {
            $this->sendKillMsg($killJobId);
            $this->remove($killJobId);
            if ($running = JobRunContent::getId($killJobId)) {
                $this->kill($killJobId, $running->getLogId(), 'Job toStop, stopReason:scheduling center kill job.');
            }
            return;
        }

        // run job
        $jobId = $runRequest->getJobId();
        $running = JobRunContent::getId($jobId);
        if ($runRequest->isCoverLater() && $running) {
            $this->callback($runRequest, 500, 'block strategy effect：Discard Later');
            return;
        }
        if ($runRequest->isCoverEarly()) {
            $key = 'coverEarlyJob_' . $jobId;
            $this->channels[$key] ??= new Channel(500);
            $this->channels[$key]->push($runRequest, 5);
            $this->coverEarlyJobLoop($key);
            return;
        }
        $this->pushJob($jobId, $runRequest);
    }

    protected function coverEarlyJobLoop(string $key): void
    {
        $mark = $this->mark[$key] ?? false;
        if ($mark) {
            return;
        }
        $this->mark[$key] = true;

        Coroutine::create(function () use ($key) {
            try {
                while (true) {
                    $channels = $this->channels[$key] ?? null;
                    $runRequest = $channels?->pop(600);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    $jobId = $runRequest->getJobId();
                    $running = JobRunContent::getId($jobId);
                    if ($running) {
                        $this->kill($jobId, $running->getLogId(), 'block strategy effect：Cover Early [job running, killed]');
                        JobRunContent::yield($running->getLogId(), 6);
                    }
                    if ($channels->length() > 1) {
                        $this->callback($runRequest, 500, 'block strategy effect：Cover Early [job running, killed]');
                        continue;
                    }
                    $this->pushJob($jobId, $runRequest);
                }
            } finally {
                $this->removeCoverEarlyJob($key);
            }
        });
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
                    $runRequest = $channels?->pop(600);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                    JobRunContent::yield($runRequest->getLogId());
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

    protected function removeCoverEarlyJob(string $key): void
    {
        unset($this->channels[$key], $this->mark[$key]);
    }

    protected function callback(RunRequest $runRequest, int $handleCode = 200, $handleMsg = null)
    {
        $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), $handleCode, $handleMsg);
    }

    protected function sendKillMsg(int $jobId): void
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

    /**
     * push job.
     */
    protected function pushJob(int $jobId, ?RunRequest $runRequest): void
    {
        $this->channels[$jobId] ??= new Channel(1000);
        $this->channels[$jobId]->push($runRequest, 5);
        $this->loop($jobId);
    }
}
