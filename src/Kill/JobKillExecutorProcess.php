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

    public function getPidArr(int $jobId, int $logId = 0): array
    {
        $logIdStr = $logId > 0 ? "_{$logId}" : '';
        $cmd = sprintf('ps aux | grep %s_%s%s | grep -v grep', self::PROCESS_PREFIX_TITLE, $jobId, $logIdStr);
        $processTitlesStr = shell_exec($cmd);
        if (empty($processTitlesStr)) {
            return [];
        }
        $processTitles = explode(PHP_EOL, $processTitlesStr);
        $data = [];
        foreach ($processTitles as $processTitle) {
            $pattern = sprintf('/%s_(.*)_end/', static::PROCESS_PREFIX_TITLE);
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
        foreach ($processTitleArr as $processTitle) {
            $pid = $processTitle['pid'];
            $logId = $processTitle['logId'];
            $logDateTime = $processTitle['logDateTime'];

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
        $process_title = sprintf(self::PROCESS_PREFIX_TITLE . '_%s_%s_%s_%s_end', $runRequest->getJobId(), $runRequest->getLogId(), $runRequest->getLogDateTime(), getmypid());
        cli_set_process_title($process_title);
    }

    public function remove(int $jobId, int $logId): void {}
}
