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

use Hyperf\Coroutine\Coroutine;
use Hyperf\XxlJob\JobCommand;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Hyperf\XxlJob\Requests\RunRequest;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class JobExecutorProcess extends AbstractJobExecutor
{
    protected static array $content = [];

    public function isRun(int $jobId): bool
    {
        $infoArr = $this->getJobFileInfo($jobId);
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
        $infoArr = $this->getJobFileInfo($jobId);
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
        $this->executeCommand($request);
    }

    public function putJobFileInfo(int $pid, RunRequest $request): string
    {
        $filename = $this->config->getLogFileDir() . sprintf('jobId_%s.info', $request->getJobId());
        $data['pid'] = $pid;
        $data['createTime'] = time();
        $data['runRequest'] = $request;
        file_put_contents($filename, json_encode($data));
        return $filename;
    }

    public function getJobFileInfo(int $jobId): array
    {
        $data = [];
        $path = $this->config->getLogFileDir() . sprintf('jobId_%s.info', $jobId);
        if (file_exists($path)) {
            $strInfo = file_get_contents($path);
            $infoArr = json_decode($strInfo, true);
            $data['pid'] = $infoArr['pid'];
            $data['createTime'] = $infoArr['createTime'];
            $data['runRequest'] = RunRequest::create($infoArr['runRequest']);
            $data['filePath'] = $path;
            return $data;
        }
        return $data;
    }

    public function executeCommand(RunRequest $request): void
    {
        $command = $this->config->getStartCommand();
        $command[] = JobCommand::COMMAND_NAME;
        $command[] = '-j';
        $command[] = $request->getJobId();
        $command[] = '-l';
        $command[] = $request->getLogId();
        $this->stdoutLogger->debug('XXL-JOB execute commands:' . implode(' ', $command));
        Coroutine::create(function () use ($command, $request) {
            try {
                JobContext::setJobLogId($request->getLogId());
                $executorTimeout = $request->getExecutorTimeout();
                $process = new Process($command, timeout: $executorTimeout > 0 ? $executorTimeout : null);
                $process->start();
                $filename = $this->putJobFileInfo($process->getPid(), $request);
                $process->wait(
                    // function ($type, $buffer): void {
                    //     $buffer = trim($buffer);
                    //     if ($type === Process::ERR) {
                    //         $this->stdoutLogger->error($buffer);
                    //     } else {
                    //         $this->stdoutLogger->info($buffer);
                    //     }
                    // }
                );
            } catch (ProcessSignaledException $e) {
                $message = sprintf('XXL-JOB: JobId:%s LogId:%s warning:%s', $request->getJobId(), $request->getLogId(), $e->getMessage());
                $this->stdoutLogger->warning($message);
            } catch (ProcessTimedOutException) {
                $msg = 'scheduling center kill job. [job running, killed]';
                $this->jobExecutorLogger->warning($msg);
                $this->stdoutLogger->warning($msg . ' JobId:' . $request->getJobId());
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $msg);
            } finally {
                ! empty($filename) && @unlink($filename);
                JobRunContent::remove($request->getJobId(), $request->getLogId());
            }
        });
    }
}
