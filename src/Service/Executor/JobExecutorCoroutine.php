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

use Hyperf\Engine\Constant;
use Hyperf\Engine\Coroutine;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Requests\RunRequest;

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
        if (Constant::ENGINE == 'Swoole' && swoole_version() >= '6.1.0') {
            $time = time();
            while (\Swoole\Coroutine::exists($cid)) {
                \Swoole\Coroutine::cancel($cid, true);
                \Swoole\Coroutine::sleep(0.3);
                if (time() - $time > 5) {
                    break;
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
            $this->apiRequest->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $msg);
        }
        return true;
    }

    public function run(RunRequest $request, ?callable $callback): void
    {
        // executorTimeout
        $executorTimeout = $request->getExecutorTimeout();
        if ($executorTimeout > 0) {
            Coroutine::create(function () use ($request) {
                $result = JobRunContent::yield($request->getLogId(), $request->getExecutorTimeout());
                if ($result === false) {
                    $this->kill($request->getJobId(), $request->getLogId(), 'scheduling center kill job. [job running, killed]');
                }
            });
        }

        $this->executeCoroutine($request, $callback);
    }

    public function executeCoroutine(RunRequest $request, callable $callback): int
    {
        return \Hyperf\Coroutine\Coroutine::create(function () use ($request, $callback) {
            $this->execute($request, $callback);
        });
    }
}
