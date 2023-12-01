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
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Logger\JobExecutorFileLogger;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\ProcessTitle;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Run\JobContent;

class JobKillExecutorProcess implements JobKillExecutorInterface
{

    public function __construct(
        protected JobContent $jobKillContent,
        protected StdoutLoggerInterface $stdoutLogger,
        protected JobExecutorFileLogger $fileLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
    ) {}

    public function getPidArr(int $jobId, int $logId = 0): array
    {
        $logIdStr = $logId > 0 ? "_{$logId}" : '';
        $cmd = sprintf('ps aux | grep %s_%s%s | grep -v grep', ProcessTitle::PROCESS_PREFIX_TITLE, $jobId, $logIdStr);
        $processTitlesStr = shell_exec($cmd);
        if (empty($processTitlesStr)) {
            return [];
        }
        $processTitles = explode(PHP_EOL, $processTitlesStr);
        $data = [];
        foreach ($processTitles as $processTitle) {
            $pattern = sprintf('/%s_(.*)_end/', ProcessTitle::PROCESS_PREFIX_TITLE);
            preg_match($pattern, $processTitle, $matches);
            if ($matches[1] ?? '') {
                [$jobId,$logId,$logDateTime,$pid] = explode('_', $matches[1]);
                $data[] = [
                    'jobId' => (int) $jobId,
                    'logId' => (int) $logId,
                    'logDateTime' => (int) $logDateTime,
                    'pid' => $pid,
                ];
            }
        }
        return $data;
    }

    public function isRun(int $jobId): bool
    {
        return ! empty($this->getPidArr($jobId));
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $processTitleArr = $this->getPidArr($jobId, $logId);
        if (empty($processTitleArr)) {
            $this->stdoutLogger->warning('xxl-job task has ended');
            return false;
        }
        $bool = true;
        foreach ($processTitleArr as $processTitle) {
            $pid = $processTitle['pid'];
            $logId = $processTitle['logId'];
            $logDateTime = $processTitle['logDateTime'];
            $bool = true;
            if($pid == -1){
                $bool = false;
                $this->stdoutLogger->error('xxl-job kill error, the job is being started');
                continue;
            }
            $result = shell_exec("kill -9 {$pid}");
            if ($result) {
                $bool = false;
                $this->stdoutLogger->error('xxl-job kill error:' . $result);
                continue;
            }
            JobContext::setJobLogId($logId);
            if ($msg) {
                $this->jobExecutorLogger->info($msg);
                $this->apiRequest->callback($logId, $logDateTime, 500, $msg);
            }
        }
        return $bool;
    }

    public function setJobId(int $jobId, int $logId, RunRequest $runRequest): void
    {
        ProcessTitle::setByRunRequest($runRequest);
    }

    public function remove(int $jobId, int $logId): void {}
}
