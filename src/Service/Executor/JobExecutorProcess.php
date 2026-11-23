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
use Hyperf\XxlJob\Requests\RunRequest;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

use const DIRECTORY_SEPARATOR;

class JobExecutorProcess extends AbstractJobExecutor
{
    protected static array $content = [];

    public function isRun(int $jobId): bool
    {
        $runProcess = JobRunContent::getId($jobId)?->getExtension('process');
        if ($runProcess instanceof Process && $runProcess->isRunning()) {
            return true;
        }

        $info = $this->getJobFileInfo($jobId);
        if ($info === [] || $info['executorType'] !== JobRunInfoStore::TYPE_PROCESS) {
            return false;
        }
        if ($this->jobRunInfoStore()->isRun($info)) {
            return true;
        }
        $this->jobRunInfoStore()->remove($jobId, $info['logId']);
        return false;
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $runRequest = JobRunContent::getId($jobId);
        $info = $this->getJobFileInfo($jobId);
        if (! $runRequest) {
            if ($info === []) {
                return true;
            }
            $runRequest = $info['runRequest'];
        }
        $currentLogId = $runRequest->getLogId();
        if ($logId > 0 && $logId !== $currentLogId) {
            // 终止旧 logId 时不能误伤同 jobId 已启动的新任务
            return true;
        }
        if ($info !== [] && $info['logId'] !== $currentLogId && ! $runRequest->getExtension('process') instanceof Process) {
            // 本地只剩旧请求且没有进程对象时，共享文件里的新 logId 不能作为旧任务的终止目标
            return true;
        }
        $logId = $currentLogId;

        $runProcess = $runRequest->getExtension('process');
        $pid = $runProcess instanceof Process ? $runProcess->getPid() : ($info['pid'] ?? null);
        if (! $runProcess instanceof Process) {
            if ($info === [] || $info['executorType'] !== JobRunInfoStore::TYPE_PROCESS) {
                return false;
            }
            if (! $this->jobRunInfoStore()->isRun($info)) {
                if ($this->jobRunInfoStore()->hasLiveProcess($info)) {
                    $this->stdoutLogger->error(sprintf('XXL-JOB JobId:%s LogId:%s process identity mismatch; refusing to kill a reused pid.', $jobId, $logId));
                    return false;
                }
                $this->jobRunInfoStore()->remove($jobId, $logId);
                return true;
            }
            // 远程只有 PID 数字，没有本地 Process 对象证明身份，缺失启动标识时宁可拒绝也不能误杀
            if (($info['pidStartTime'] ?? null) === null) {
                $this->stdoutLogger->error(sprintf('XXL-JOB JobId:%s LogId:%s cannot verify the process identity.', $jobId, $logId));
                return false;
            }
        }

        try {
            if ('\\' === DIRECTORY_SEPARATOR) {
                if (! $runProcess instanceof Process) {
                    return false;
                }
                $runProcess->stop();
                $terminated = ! $runProcess->isRunning();
            } elseif (is_int($pid) && $pid > 0) {
                $realPids = $this->getPidByPpid($pid);
                $realPids[] = (string) $pid;
                $process = new Process(['kill', '-9', ...$realPids]);
                $process->run();
                $terminated = $process->isSuccessful();
            } else {
                $terminated = false;
            }

            if (! $terminated) {
                $stillRunning = $runProcess instanceof Process
                    ? $runProcess->isRunning()
                    : $this->jobRunInfoStore()->isRun($info);
                if ($stillRunning) {
                    $this->stdoutLogger->error(sprintf('XXL-JOB JobId:%s LogId:%s could not be killed.', $jobId, $logId));
                    return false;
                }
            }
        } catch (Throwable $throwable) {
            $this->stdoutLogger->error($throwable->getMessage());
            return false;
        }

        $this->jobRunInfoStore()->remove($jobId, $logId);
        if ($msg) {
            JobContext::setJobLogId($logId);
            $this->jobExecutorLogger->warning($msg);
            try {
                $this->apiRequest->callback($logId, $runRequest->getLogDateTime(), 500, $msg);
            } catch (Throwable $throwable) {
                $this->stdoutLogger->error($throwable->getMessage());
            }
        }
        return true;
    }

    public function getPidByPpid(int $ppid): array
    {
        $pids = shell_exec("pgrep -P {$ppid}");
        $pids = trim((string) $pids);
        return $pids ? explode(PHP_EOL, $pids) : [];
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        $this->executeCommand($request);
    }

    public function putJobFileInfo(int $pid, RunRequest $request): string
    {
        return $this->jobRunInfoStore()->put($request, JobRunInfoStore::TYPE_PROCESS, $pid);
    }

    public function getJobFileInfo(int $jobId): array
    {
        return $this->jobRunInfoStore()->get($jobId);
    }

    public function removeJobFileInfo(int $jobId, int $logId): void
    {
        $this->jobRunInfoStore()->remove($jobId, $logId);
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
                $message = '';
                JobContext::setJobLogId($request->getLogId());
                $executorTimeout = $request->getExecutorTimeout();
                $process = new Process($command, timeout: $executorTimeout > 0 ? $executorTimeout : null);
                $process->start();
                $request->setExtension('process', $process);
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
                $message = sprintf('XXL-JOB: JobId:%s LogId:%s %s', $request->getJobId(), $request->getLogId(), $e->getMessage());
                $this->stdoutLogger->warning($message);
            } catch (ProcessTimedOutException) {
                $message = 'scheduling center kill job. [job running, killed]';
                $this->jobExecutorLogger->warning($message);
                $this->stdoutLogger->warning($message . ' JobId:' . $request->getJobId());
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
            } finally {
                try {
                    ! empty($filename) && $this->removeJobFileInfo($request->getJobId(), $request->getLogId());
                    if (empty($message) && ! empty($process) && ! $process->isSuccessful()) {
                        $message = $process->getErrorOutput();
                        $warningMsg = sprintf('XXL-JOB: JobId:%s LogId:%s %s', $request->getJobId(), $request->getLogId(), $message);
                        $this->stdoutLogger->warning($warningMsg);
                    }
                } finally {
                    JobRunContent::remove($request->getJobId(), $request->getLogId());
                }
            }
        });
    }
}
