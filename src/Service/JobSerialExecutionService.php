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
use Hyperf\XxlJob\Process\JobDispatcherProcess;
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

    /**
     * @var array<int, RunRequest> 已出队但尚未执行完成或完成回调的请求
     */
    protected array $processing = [];

    public function handle(?RunRequest $runRequest = null, int $killJobId = 0): void
    {
        // kill job
        if ($killJobId > 0) {
            $this->sendKillMsg($killJobId);
            $this->remove($killJobId);
            $this->removeCoverEarlyJob('coverEarlyJob_' . $killJobId);
            $running = JobRunContent::getId($killJobId);
            $logId = $running?->getLogId() ?? 0;
            // 本地数组为空不代表其他 worker 没有运行任务，kill 必须继续查询共享运行信息
            if ($this->kill($killJobId, $logId, 'Job toStop, stopReason:scheduling center kill job.') === false) {
                throw new XxlJobException(sprintf('JobId:%s could not be killed in the target process.', $killJobId));
            }
            return;
        }

        // run job
        if ($runRequest && ! JobDispatcherProcess::isAcceptingJobs()) {
            // max_process_lifetime 进入退出阶段后不再接单，避免进程永远等不到队列清空
            $this->processing[$runRequest->getLogId()] = $runRequest;
            try {
                $this->callback($runRequest, 500, 'XXL-JOB dispatcher is shutting down; please retry later.');
            } finally {
                unset($this->processing[$runRequest->getLogId()]);
            }
            return;
        }
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

    public function hasPendingJobs(): bool
    {
        if ($this->processing !== [] || JobRunContent::getRunningJobIds() !== []) {
            return true;
        }
        foreach ($this->channels as $channel) {
            if ($channel->length() > 0) {
                return true;
            }
        }
        return false;
    }

    public function failPendingJobs(string $message): void
    {
        $requests = $this->processing;
        foreach (JobRunContent::getRunningJobIds() as $jobId) {
            $request = JobRunContent::getId($jobId);
            if ($request) {
                $requests[$request->getLogId()] = $request;
            }
        }
        foreach ($this->channels as $channel) {
            while (! $channel->isEmpty()) {
                $request = $channel->pop(0.01);
                if (! $request instanceof RunRequest) {
                    break;
                }
                $requests[$request->getLogId()] = $request;
            }
        }
        $this->channels = [];
        $this->mark = [];
        $this->processing = [];

        $data = [];
        foreach ($requests as $request) {
            $data[] = [
                'logId' => $request->getLogId(),
                'logDateTime' => $request->getLogDateTime(),
            ];
        }
        $this->apiRequest->multipleCallback($data, 500, $message);
    }

    protected function coverEarlyJobLoop(string $key): void
    {
        $mark = $this->mark[$key] ?? false;
        if ($mark) {
            return;
        }
        $channel = $this->channels[$key] ?? null;
        if (! $channel) {
            return;
        }
        $this->mark[$key] = true;

        Coroutine::create(function () use ($key, $channel) {
            try {
                while ($this->isCurrentChannel($key, $channel)) {
                    if ($channel->length() > 1) {
                        $runRequest = $channel->pop(60);
                        if (! $runRequest instanceof RunRequest) {
                            return;
                        }
                        $this->processing[$runRequest->getLogId()] = $runRequest;
                        Coroutine::create(function () use ($runRequest) {
                            try {
                                $this->callback($runRequest, 500, 'block strategy effect：Cover Early [job running, killed]');
                            } finally {
                                unset($this->processing[$runRequest->getLogId()]);
                            }
                        });
                    } else {
                        sleep(1);
                    }
                }
            } finally {
                $this->removeCoverEarlyJob($key, $channel);
            }
        });

        Coroutine::create(function () use ($key, $channel) {
            try {
                while (true) {
                    $runRequest = $channel->pop(60);
                    if (! $runRequest instanceof RunRequest) {
                        return;
                    }
                    $this->processing[$runRequest->getLogId()] = $runRequest;
                    try {
                        if (! $this->isCurrentChannel($key, $channel)) {
                            // 请求虽已出队，但 kill 已使本代失效，不能再转入普通队列执行
                            $this->callback($runRequest, 500, 'scheduling center kill job. [job not executed, in the job queue, killed.]');
                            return;
                        }
                        $jobId = $runRequest->getJobId();
                        $running = JobRunContent::getId($jobId);
                        if ($running) {
                            if ($this->kill($jobId, $running->getLogId(), 'block strategy effect：Cover Early [job running, killed]') === false) {
                                $this->callback($runRequest, 500, 'block strategy effect：Cover Early [failed to kill the running job]');
                                continue;
                            }
                            // kill 只代表已发起终止；旧任务未收尾前不能启动新任务，避免同 jobId 并发
                            if (! JobRunContent::yield($running->getLogId(), 6)) {
                                $this->callback($runRequest, 500, 'block strategy effect：Cover Early [timed out waiting for the running job to stop]');
                                continue;
                            }
                        }
                        if (! $this->isCurrentChannel($key, $channel)) {
                            $this->callback($runRequest, 500, 'scheduling center kill job. [job not executed, in the job queue, killed.]');
                            return;
                        }
                        if ($channel->length() > 0) {
                            $this->callback($runRequest, 500, 'block strategy effect：Cover Early [job running, killed]');
                            continue;
                        }
                        $this->pushJob($jobId, $runRequest);
                    } finally {
                        unset($this->processing[$runRequest->getLogId()]);
                    }
                }
            } finally {
                $this->removeCoverEarlyJob($key, $channel);
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
                    $this->processing[$runRequest->getLogId()] = $runRequest;
                    try {
                        try {
                            $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                        } catch (Throwable $throwable) {
                            // 分发异常必须就地消化：这里是 dispatcher 里的协程根，逃出去会直接杀掉整个进程
                            $this->handleDispatchFailure($runRequest, $throwable);
                            continue;
                        }
                        JobRunContent::yield($runRequest->getLogId());
                    } finally {
                        // 排队计数可能已转交给 JobRunContent；只有执行及失败回调都结束才移除分发中标记
                        unset($this->processing[$runRequest->getLogId()]);
                    }
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

    protected function removeCoverEarlyJob(string $key, ?Channel $channel = null): void
    {
        if ($channel !== null && ! $this->isCurrentChannel($key, $channel)) {
            return;
        }
        unset($this->channels[$key], $this->mark[$key]);
    }

    protected function isCurrentChannel(string $key, Channel $channel): bool
    {
        return ($this->channels[$key] ?? null) === $channel;
    }

    protected function callback(RunRequest $runRequest, int $handleCode = 200, $handleMsg = null)
    {
        $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), $handleCode, $handleMsg);
    }

    protected function sendKillMsg(int $jobId): void
    {
        $data = [];
        foreach ([$jobId, 'coverEarlyJob_' . $jobId] as $key) {
            $channel = $this->channels[$key] ?? null;
            while ($channel && ! $channel->isEmpty()) {
                // isEmpty 与 pop 之间 loop 可能抢走最后一条消息；false 不是可回调的 RunRequest
                $runRequest = $channel->pop(0.01);
                if (! $runRequest instanceof RunRequest) {
                    break;
                }
                $data[] = [
                    'logId' => $runRequest->getLogId(),
                    'logDateTime' => $runRequest->getLogDateTime(),
                ];
            }
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
