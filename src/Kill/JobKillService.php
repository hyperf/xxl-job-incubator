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

use Hyperf\Engine\Constant;
use Hyperf\XxlJob\ApiRequest;
use Psr\Container\ContainerInterface;

class JobKillService
{
    public function __construct(
        protected ContainerInterface $container,
        protected ApiRequest $apiRequest,
    ) {}

    public function getKillExecutor(): JobKillExecutorInterface
    {
        $classname = match (Constant::ENGINE) {
            'Swow' => JobKillExecutorSwow::class,
            default => JobKillExecutorProcess::class,
        };
        return $this->container->get($classname);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): void
    {
        $class = $this->getKillExecutor();
        $class->kill($jobId, $logId, $msg);
    }
}
