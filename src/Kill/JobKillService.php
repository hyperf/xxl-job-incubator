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
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;

class JobKillService
{
    public function __construct(
        protected ContainerInterface $container,
        protected ApiRequest $apiRequest,
        protected Config $config
    ) {
    }

    public function getKillExecutor(): JobKillExecutorInterface
    {
        $classname = match (Constant::ENGINE) {
            'Swow' => JobKillExecutorSwow::class,
            default => JobKillExecutorProcess::class,
        };
        return $this->container->get($classname);
    }

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool
    {
        $class = $this->getKillExecutor();
        return $class->kill($jobId, $logId, $msg);
    }

    public function putProcessInfo(int $pid, RunRequest $request): string
    {
        $filename = $this->config->getLogFileDir() . sprintf('jobId_%s_logId_%s.info', $request->getJobId(), $request->getLogId());
        $data['logId'] = $request->getLogId();
        $data['logDateTime'] = $request->getLogDateTime();
        $data['jobId'] = $request->getJobId();
        $data['pid'] = $pid;
        file_put_contents($filename, json_encode($data));
        return $filename;
    }
}
