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

use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Hyperf\XxlJob\Requests\RunRequest;

class JobExecutorProcess extends AbstractJobExecutor
{
    protected static array $content = [];

    public function isRun(int $jobId): bool
    {
        $infoArr = $this->jobRun->getJobFileInfo($jobId);
        if (empty($infoArr)) {
            return false;
        }
        $createTime = $infoArr['createTime'] ?? 0;
        if (BootAppRouteListener::$AppStartTime > $createTime) {
            return false;
        }
        return true;
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $infoArr = $this->jobRun->getJobFileInfo($jobId);
        if (empty($infoArr)) {
            $this->stdoutLogger->warning('xxl-job task has ended');
            return true;
        }
        $runRequest = $infoArr['runRequest'];
        $pid = $infoArr['pid'];
        $logId = $logId ?: $runRequest->getLogId();
        $logDateTime = $runRequest->getLogDateTime();
        $bool = true;
        if (! $pid || $pid == -1) {
            @unlink($infoArr['filePath']);
            $bool = false;
            $this->stdoutLogger->error('xxl-job kill error, the job is being started');
        }
        $result = shell_exec("kill -9 {$pid}");
        if ($result) {
            $bool = false;
            $this->stdoutLogger->error("xxl-job kill error with PID {$pid}");
        } else {
            // $this->stdoutLogger->error("xxl-job kill error with PID {$pid}, logId {$logId}");
            @unlink($infoArr['filePath']);
        }

        if ($bool && $msg) {
            JobContext::setJobLogId($logId);
            $this->jobExecutorLogger->warning($msg);
            $this->apiRequest->callback($logId, $logDateTime, 500, $msg);
        }
        return $bool;
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        $this->jobRun->executeCommand($request);
    }
}
