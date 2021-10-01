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
namespace Hyperf\XxlJob;

class JobHandlerManager
{
    protected array $jobHandlers = [];

    public function getJobHandlers(string $jobName): ?JobHandlerDefinition
    {
        return $this->jobHandlers[$jobName] ?? null;
    }

    public function registerJobHandler(string $jobName, JobHandlerDefinition $definition): void
    {
        $this->jobHandlers[$jobName] = $definition;
    }
}
