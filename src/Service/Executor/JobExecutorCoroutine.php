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
use Hyperf\Engine\Constant;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Requests\RunRequest;
use RuntimeException;
use Throwable;

class JobExecutorCoroutine extends AbstractJobExecutor
{
    public function isRun(int $jobId): bool
    {
        if (JobRunContent::has($jobId)) {
            return true;
        }
        $info = $this->jobRunInfoStore()->get($jobId);
        if ($info === [] || $info['executorType'] !== JobRunInfoStore::TYPE_COROUTINE) {
            return false;
        }
        if ($this->jobRunInfoStore()->isRun($info)) {
            return true;
        }
        // 属主进程已消失时清理残留，避免 dispatcher 重启后永久判忙
        $this->jobRunInfoStore()->remove($jobId, $info['logId']);
        return false;
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $runRequest = JobRunContent::getId($jobId);
        if (empty($runRequest)) {
            $info = $this->jobRunInfoStore()->get($jobId);
            if ($info === []) {
                return true;
            }
            if ($info['executorType'] === JobRunInfoStore::TYPE_COROUTINE && ! $this->jobRunInfoStore()->isRun($info)) {
                $this->jobRunInfoStore()->remove($jobId, $info['logId']);
                return true;
            }
            // 其他 worker 不能使用文件里的 cid 取消本进程协程，必须明确报告终止失败
            $this->stdoutLogger->error(sprintf('XXL-JOB JobId:%s LogId:%s is not running in the current process.', $jobId, $logId));
            return false;
        }
        $cid = $runRequest->getExtension('cid');
        if (! is_int($cid) || $cid <= 0) {
            // 没有本进程协程 ID 时不能声称已终止，尤其不能对 exists(null) 继续调用
            $this->stdoutLogger->error(sprintf('XXL-JOB JobId:%s LogId:%s cannot be killed without a local coroutine id.', $jobId, $logId));
            return false;
        }
        if (Constant::ENGINE == 'Swoole' && swoole_version() >= '6.1.0') {
            $time = time();
            while (\Swoole\Coroutine::exists($cid)) {
                try {
                    \Swoole\Coroutine::cancel($cid, true);
                    \Swoole\Coroutine::sleep(0.3);
                    if (time() - $time > 5) {
                        break;
                    }
                } catch (Throwable $throwable) {
                    $this->stdoutLogger->error($throwable->getMessage());
                }
            }
        } elseif (Constant::ENGINE == 'Swow') {
            \Swow\Coroutine::get($cid)?->kill();
        } else {
            $this->stdoutLogger->error('the current mode does not support killing jobs');
            return false;
        }

        if ($msg) {
            JobContext::setJobLogId($logId);
            $this->jobExecutorLogger->warning($msg);
            $this->callbackSafely($runRequest, 500, $msg);
        }
        return true;
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        $published = false;
        try {
            // 仅 Bean 协程任务在这里登记；脚本虽复用 executeCoroutine，但实际由进程执行器管理
            $this->jobRunInfoStore()->put($request, JobRunInfoStore::TYPE_COROUTINE);
            $published = true;
            $executorTimeout = $request->getExecutorTimeout();
            if ($executorTimeout > 0) {
                Coroutine::create(function () use ($request) {
                    $this->watchTimeout($request);
                });
            }

            if ($this->executeCoroutine($request, $callback, true) < 0) {
                throw new RuntimeException('failed to create XXL-JOB execution coroutine');
            }
        } catch (Throwable $throwable) {
            if ($published) {
                $this->jobRunInfoStore()->remove($request->getJobId(), $request->getLogId());
            }
            throw $throwable;
        }
    }

    public function executeCoroutine(RunRequest $request, callable $callback, bool $clearRunInfo = false): int
    {
        return Coroutine::create(function () use ($request, $callback, $clearRunInfo) {
            try {
                $this->execute($request, $callback);
            } finally {
                if ($clearRunInfo) {
                    // 只清理本 logId；旧任务收尾可能晚于新一代信息发布
                    $this->jobRunInfoStore()->remove($request->getJobId(), $request->getLogId());
                }
            }
        });
    }

    protected function watchTimeout(RunRequest $request): void
    {
        try {
            $result = JobRunContent::yield($request->getLogId(), $request->getExecutorTimeout());
            if ($result === false && ! $this->kill($request->getJobId(), $request->getLogId(), 'scheduling center kill job. [job running, killed]')) {
                throw new RuntimeException('the running job could not be killed');
            }
        } catch (Throwable $throwable) {
            // watcher 在独立协程运行：终止失败必须回调，但异常不能逃出协程根打死 dispatcher
            $message = sprintf(
                'XXL-JOB JobId:%s LogId:%s timeout watcher failed: %s',
                $request->getJobId(),
                $request->getLogId(),
                $throwable->getMessage()
            );
            $this->stdoutLogger->error($message);
            $this->callbackSafely($request, 500, $message);
        }
    }
}
