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

use Hyperf\Engine\Constant;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorInterface;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Psr\Container\ContainerInterface;

class BaseService
{
    public function __construct(
        protected ContainerInterface $container,
        protected GlueHandlerManager $glueHandlerManager,
        protected JobHandlerManager $jobHandlerManager,
        protected ApiRequest $apiRequest,
    ) {
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $class = $this->getKillExecutor();
        return $class->kill($jobId, $logId, $msg);
    }

    public function isRun(int $jobId): bool
    {
        $class = $this->getKillExecutor();
        return $class->isRun($jobId);
    }

    protected function getKillExecutor(): JobExecutorInterface
    {
        $classname = match (Constant::ENGINE) {
            'Swow' => JobExecutorCoroutine::class,
            default => JobExecutorProcess::class,
        };
        return $this->container->get($classname);
    }
}
