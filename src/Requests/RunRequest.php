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
namespace Hyperf\XxlJob\Requests;

class RunRequest extends BaseRequest
{
    protected int $jobId;  // 任务ID

    protected string $executorHandler; // 任务标识

    protected string $executorParams; // 任务参数

    protected string $executorBlockStrategy; // 任务阻塞策略，可选值参考 com.xxl.job.core.enums.ExecutorBlockStrategyEnum

    protected int $executorTimeout; // 任务超时时间，单位秒，大于零时生效

    protected int $logId; // 本次调度日志ID

    protected int $logDateTime; // 本次调度日志时间

    protected string $glueType; // 任务模式，可选值参考 com.xxl.job.core.glue.GlueTypeEnum

    protected string $glueSource;  // GLUE脚本代码

    protected int $glueUpdatetime; // GLUE脚本更新时间，用于判定脚本是否变更以及是否需要刷新

    protected int $broadcastIndex; // 分片参数：当前分片

    protected int $broadcastTotal; // 分片参数：总分片

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getExecutorHandler(): string
    {
        return $this->executorHandler;
    }

    public function getExecutorParams(): string
    {
        return $this->executorParams;
    }

    public function getExecutorBlockStrategy(): string
    {
        return $this->executorBlockStrategy;
    }

    public function getExecutorTimeout(): int
    {
        return $this->executorTimeout;
    }

    public function getLogId(): int
    {
        return $this->logId;
    }

    public function getLogDateTime(): int
    {
        return $this->logDateTime;
    }

    public function getGlueType(): string
    {
        return $this->glueType;
    }

    public function getGlueSource(): string
    {
        return $this->glueSource;
    }

    public function getGlueUpdatetime(): int
    {
        return $this->glueUpdatetime;
    }

    public function getBroadcastIndex(): int
    {
        return $this->broadcastIndex;
    }

    public function getBroadcastTotal(): int
    {
        return $this->broadcastTotal;
    }
}
