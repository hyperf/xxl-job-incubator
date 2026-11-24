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

namespace Hyperf\XxlJob\Service;

use Hyperf\XxlJob\Api\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\Handler\JobHandlerManager;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorInterface;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Hyperf\XxlJob\Service\Executor\JobRunInfoStore;
use Psr\Container\ContainerInterface;

class BaseService
{
    private ?JobRunInfoStore $runInfoStore = null;

    public function __construct(
        protected ContainerInterface $container,
        protected GlueHandlerManager $glueHandlerManager,
        protected JobHandlerManager $jobHandlerManager,
        protected ApiRequest $apiRequest,
        protected Config $config,
    ) {
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        if (! JobRunContent::has($jobId) && $this->jobRunInfoStore()->get($jobId) === []) {
            return true;
        }
        return $this->getJobExecutor($jobId)->kill($jobId, $logId, $msg);
    }

    public function isRun(int $jobId): bool
    {
        if (! JobRunContent::has($jobId) && $this->jobRunInfoStore()->get($jobId) === []) {
            return false;
        }
        return $this->getJobExecutor($jobId)->isRun($jobId);
    }

    protected function getJobExecutor(int $jobId): JobExecutorInterface
    {
        $info = $this->jobRunInfoStore()->get($jobId);
        if ($info !== []) {
            // 文件存在不再等同于进程任务；脚本、进程 Bean 与协程 Bean 必须显式分派
            $classname = $info['executorType'] === JobRunInfoStore::TYPE_PROCESS
                ? JobExecutorProcess::class
                : JobExecutorCoroutine::class;
            return $this->container->get($classname);
        }

        $runRequest = JobRunContent::getId($jobId);
        $classname = $runRequest?->getExtension('process')
            ? JobExecutorProcess::class
            : JobExecutorCoroutine::class;
        return $this->container->get($classname);
    }

    protected function jobRunInfoStore(): JobRunInfoStore
    {
        return $this->runInfoStore ??= new JobRunInfoStore($this->config);
    }
}
