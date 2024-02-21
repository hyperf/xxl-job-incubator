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

use Hyperf\XxlJob\Exception\RegisterJobHandlerException;

class JobHandlerManager
{
    protected array $jobHandlers = [];

    public function getJobHandlers(string $jobName): ?JobHandlerDefinition
    {
        return $this->jobHandlers[$jobName] ?? null;
    }

    public function registerJobHandler(string $jobName, JobHandlerDefinition $definition): void
    {
        if (isset($this->jobHandlers[$jobName])) {
            throw new RegisterJobHandlerException(sprintf('xxl-job jobHandler %s naming conflicts.', $jobName));
        }
        $this->jobHandlers[$jobName] = $definition;
    }
}
