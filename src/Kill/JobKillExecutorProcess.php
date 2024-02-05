<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Kill;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Logger\JobExecutorFileLogger;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
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
        protected Config $xxlConfig,
    ) {}

    public function getPidArr(int $jobId, int $logId = 0): array
    {
        $filename = $this->xxlConfig->getLogFileDir() . sprintf('jobId_%s_logId_%s.info', $jobId, $logId > 0 ? $logId : '*');
        return glob($filename);
    }

    public function isRun(int $jobId): bool
    {
        return ! empty($this->getPidArr($jobId));
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $processFileArr = $this->getPidArr($jobId, $logId);
        if (empty($processFileArr)) {
            $this->stdoutLogger->warning('xxl-job task has ended');
            return false;
        }
        $bool = true;
        foreach ($processFileArr as $processFile) {
            $strInfo = file_get_contents($processFile);
            $infoArr = json_decode($strInfo, true);
            $pid = $infoArr['pid'];
            $logId = $infoArr['logId'];
            $logDateTime = $infoArr['logDateTime'];
            $bool = true;
            if (! $pid || $pid == -1) {
                @unlink($processFile);
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
            @unlink($processFile);
            JobContext::setJobLogId($logId);
            if ($msg) {
                $this->jobExecutorLogger->info($msg);
                $this->apiRequest->callback($logId, $logDateTime, 500, $msg);
            }
        }
        return $bool;
    }

    public function setJobId(int $jobId, int $logId, RunRequest $runRequest): void {}

    public function remove(int $jobId, int $logId): void {}
}
