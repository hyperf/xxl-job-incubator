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
        return JobRunContent::has($jobId);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $runRequest = JobRunContent::getId($jobId);
        if (empty($runRequest)) {
            return true;
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
        // executorTimeout
        $executorTimeout = $request->getExecutorTimeout();
        if ($executorTimeout > 0) {
            Coroutine::create(function () use ($request) {
                $this->watchTimeout($request);
            });
        }

        $this->executeCoroutine($request, $callback);
    }

    public function executeCoroutine(RunRequest $request, callable $callback): int
    {
        return Coroutine::create(function () use ($request, $callback) {
            $this->execute($request, $callback);
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

    protected function callbackSafely(RunRequest $runRequest, int $handleCode, string $message): void
    {
        try {
            $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), $handleCode, $message);
        } catch (Throwable $throwable) {
            // admin 不可达只影响回调本身，不能把超时 watcher 或 kill 调用协程一起带崩
            $this->stdoutLogger->error($throwable->getMessage());
        }
    }
}
