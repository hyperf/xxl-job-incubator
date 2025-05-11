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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\JobContent;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Hyperf\XxlJob\Logger\JobExecutorFileLogger;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;

class JobExecutorProcess implements JobExecutorInterface
{
    protected static array $content = [];

    public function __construct(
        protected JobContent $jobKillContent,
        protected StdoutLoggerInterface $stdoutLogger,
        protected JobExecutorFileLogger $fileLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected Config $xxlConfig,
        protected JobRun $run,
    ) {
    }

    public function isRun(int $jobId): bool
    {
        $processFile = $this->getProcessFile($jobId);
        if (empty($processFile)) {
            return false;
        }
        $strInfo = file_get_contents($processFile);
        $infoArr = json_decode($strInfo, true);
        // $pid = $infoArr['pid'];
        $createTime = $infoArr['createTime'] ?? 0;

        if (BootAppRouteListener::$AppStartTime > $createTime) {
            return false;
        }
        return true;
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $processFile = $this->getProcessFile($jobId);
        if (empty($processFile)) {
            $this->stdoutLogger->warning('xxl-job task has ended');
            return false;
        }
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
        }
        $result = shell_exec("kill -9 {$pid}");
        if ($result) {
            $bool = false;
            $this->stdoutLogger->error("xxl-job kill error with PID {$pid}");
        } else {
            @unlink($processFile);
            JobContent::remove($jobId);
        }

        JobContext::setJobLogId($logId);
        if ($msg) {
            $this->jobExecutorLogger->info($msg);
            $this->apiRequest->callback($logId, $logDateTime, 500, $msg);
        }
        return $bool;
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        $this->run->executeCommand($request);
    }

    protected function getProcessFile(int $jobId): string
    {
        $path = $this->xxlConfig->getLogFileDir() . sprintf('jobId_%s.info', $jobId);
        if (file_exists($path)) {
            return $path;
        }
        return '';
    }
}
