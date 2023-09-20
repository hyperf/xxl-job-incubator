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
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Run\JobContent;

class JobKillExecutorProcess implements JobKillExecutorInterface
{
    public const PROCESS_PREFIX_TITLE = 'hyperf:xxl-job';

    public function __construct(
        protected JobContent $jobKillContent,
        protected StdoutLoggerInterface $stdoutLogger,
        protected JobExecutorFileLogger $fileLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
    ) {}

    public function isRun(int $jobId): bool
    {
        $cmd = sprintf(" ps aux | grep %s_%s | grep -v grep | awk '{print $11}'", self::PROCESS_PREFIX_TITLE, $jobId);
        $processTitlesStr = shell_exec($cmd);
        return ! empty($processTitlesStr);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $logIdStr = $logId > 0 ? "_{$logId}" : '';
        $cmd = sprintf(" ps aux | grep %s_%s%s | grep -v grep | awk '{print $11}'", self::PROCESS_PREFIX_TITLE, $jobId, $logIdStr);
        $processTitlesStr = shell_exec($cmd);
        if (empty($processTitlesStr)) {
            $this->stdoutLogger->warning('xxl-job task has ended');
            return false;
        }

        $processTitleArr = explode(PHP_EOL, trim($processTitlesStr));
        foreach ($processTitleArr as $processTitle) {
            [,,$logId,$logDateTime,$pid] = explode('_', $processTitle);
            $logId = intval($logId);
            $logDateTime = intval($logDateTime);

            $result = shell_exec("kill -9 {$pid}");
            if ($result) {
                $this->stdoutLogger->error('xxl-job kill error:' . $result);
                return false;
            }
            JobContext::setJobLogId($logId);
            if ($msg) {
                $this->jobExecutorLogger->info($msg);
                $this->apiRequest->callback($logId, $logDateTime, 500, $msg);
            }
        }
        return true;
    }

    public function setJobId(int $jobId, int $logId, RunRequest $runRequest): void
    {
        $process_title = sprintf(self::PROCESS_PREFIX_TITLE . '_%s_%s_%s_%s', $runRequest->getJobId(), $runRequest->getLogId(), $runRequest->getLogDateTime(), getmypid());
        cli_set_process_title($process_title);
    }

    public function remove(int $jobId, int $logId): void {}
}
