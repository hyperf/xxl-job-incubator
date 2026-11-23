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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Channel;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Throwable;

class JobSerialExecutionService extends BaseService
{
    /**
     * @var Channel[]
     */
    protected array $channels = [];

    protected array $mark = [];

    public function handle(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        // kill job
        if ($killJobId > 0) {
            $this->sendKillMsg($killJobId);
            $this->remove($killJobId);
            $running = JobRunContent::getId($killJobId);
            $logId = $running?->getLogId() ?? 0;
            // 本地数组为空不代表其他 worker 没有运行任务，kill 必须继续查询共享运行信息
            if ($this->kill($killJobId, $logId, 'Job toStop, stopReason:scheduling center kill job.') === false) {
                throw new XxlJobException(sprintf('JobId:%s could not be killed in the target process.', $killJobId));
            }
            return;
        }

        // run job
        $jobId = $runRequest->getJobId();
        $running = JobRunContent::getId($jobId);
        // 丢弃后续调度
        if ($runRequest->isCoverLater() && $running) {
            $this->callback($runRequest, 500, 'block strategy effect：Discard Later');
            return;
        }
        // 覆盖之前调度
        if ($runRequest->isCoverEarly()) {
            $key = 'coverEarlyJob_' . $jobId;
            $this->channels[$key] ??= new Channel(500);
            $result = $this->channels[$key]->push($runRequest, 5);
            if (! $result) {
                $this->callback($runRequest, 500, 'CoverEarly job queue is full, please try again later.');
                return;
            }
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
            while (true) {
                $channels = $this->channels[$key] ?? null;
                if (! $channels) {
                    return;
                }
                if ($channels->length() > 1) {
                    $runRequest = $channels->pop(60);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    Coroutine::create(function () use ($runRequest) {
                        $this->callback($runRequest, 500, 'block strategy effect：Cover Early [job running, killed]');
                    });
                } else {
                    sleep(1);
                }
            }
        });

        Coroutine::create(function () use ($key) {
            try {
                while (true) {
                    $channels = $this->channels[$key] ?? null;
                    $runRequest = $channels?->pop(60);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    $jobId = $runRequest->getJobId();
                    $running = JobRunContent::getId($jobId);
                    if ($running) {
                        $this->kill($jobId, $running->getLogId(), 'block strategy effect：Cover Early [job running, killed]');
                        JobRunContent::yield($running->getLogId(), 6);
                    }
                    if ($channels->length() > 0) {
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
        $channel = $this->channels[$jobId] ?? null;
        if (! $channel) {
            return;
        }
        $this->mark[$jobId] = true;

        // loop 只消费启动时的 Channel；kill 后重建的同 jobId Channel 属于新一代，不能混用
        Coroutine::create(function () use ($jobId, $channel) {
            try {
                while (true) {
                    $runRequest = $channel->pop(60);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    try {
                        $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                    } catch (Throwable $throwable) {
                        // 分发异常必须就地消化：这里是 dispatcher 里的协程根，逃出去会直接杀掉整个进程
                        $this->handleDispatchFailure($runRequest, $throwable);
                        continue;
                    }
                    JobRunContent::yield($runRequest->getLogId());
                }
            } finally {
                $this->remove($jobId, $channel);
            }
        });
    }

    /**
     * 分发失败时兜底：清运行登记并补发 500，避免整个 dispatcher 陪葬、admin 日志永久停在 running。
     */
    protected function handleDispatchFailure(RunRequest $runRequest, Throwable $throwable): void
    {
        $message = sprintf(
            'XXL-JOB JobId:%s LogId:%s dispatch failed: %s',
            $runRequest->getJobId(),
            $runRequest->getLogId(),
            $throwable->getMessage()
        );
        $logger = $this->container->has(StdoutLoggerInterface::class)
            ? $this->container->get(StdoutLoggerInterface::class)
            : null;
        $logger?->error($message);

        JobRunContent::remove($runRequest->getJobId(), $runRequest->getLogId());
        try {
            $this->callback($runRequest, 500, $message);
        } catch (Throwable $callbackError) {
            // admin 不可达时只记录：回调失败不能反过来打断该 jobId 的串行队列
            $logger?->error((string) $callbackError);
        }
    }

    protected function remove(int $jobId, ?Channel $channel = null): void
    {
        // loop 收尾会带上本代 Channel；身份不一致说明 kill 后已重建，旧 loop 不能删新状态
        if ($channel !== null && ($this->channels[$jobId] ?? null) !== $channel) {
            return;
        }
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
        $result = $this->channels[$jobId]->push($runRequest, 5);
        if (! $result) {
            // Channel 满且超时，回调失败通知 Admin，防止任务静默丢失
            $this->callback($runRequest, 500, 'Job queue is full, please try again later.');
            return;
        }
        $this->loop($jobId);
    }
}
