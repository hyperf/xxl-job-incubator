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

use Hyperf\XxlJob\Requests\RunRequest;

interface JobKillExecutorInterface
{
    public function isRun(int $jobId): bool;

    public function setJobId(int $jobId, int $logId, RunRequest $runRequest): void;

    public function remove(int $jobId, int $logId): void;

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool;
}
