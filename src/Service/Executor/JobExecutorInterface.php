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

use Hyperf\XxlJob\Requests\RunRequest;

interface JobExecutorInterface
{
    public function isRun(int $jobId): bool;

    public function kill(int $jobId, int $logId = 0, string $msg = ''): bool;

    public function run(RunRequest $request, ?callable $callback): void;
}
