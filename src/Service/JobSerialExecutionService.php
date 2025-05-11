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

use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Channel;
use Hyperf\XxlJob\JobContent;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Requests\RunRequest;

class JobSerialExecutionService extends BaseService
{
    protected array $channels = [];

    protected array $mark = [];

    public function handle(JobPipeMessage $jobPipeMessage): void
    {
        if ($jobPipeMessage->KillJobId > 0) {
            $this->remove($jobPipeMessage->KillJobId);
            return;
        }
        $runRequest = $jobPipeMessage->runRequest;
        $jobId = $runRequest->getJobId();
        $this->channels[$jobId] ??= new Channel(1000);
        $this->channels[$jobId]->push($runRequest, 2);
        $this->loop($jobId);
    }

    protected function loop($jobId): void
    {
        $mark = $this->mark[$jobId] ?? false;
        if ($mark) {
            return;
        }
        $this->mark[$jobId] = true;

        Coroutine::create(function () use ($jobId) {
            while (true) {
                if (JobContent::has($jobId) || $this->isRun($jobId)) {
                    usleep(500000);
                    continue;
                }
                $runRequest = $this->channels[$jobId]?->pop(5);
                if ($runRequest instanceof RunRequest) {
                    JobContent::setJobId($jobId, $runRequest);
                    $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
                } else {
                    $this->remove($jobId);
                    return;
                }
            }
        });
    }

    protected function remove($jobId): void
    {
        $this->channels[$jobId]?->close();
        $this->channels[$jobId] = null;
        unset($this->mark[$jobId]);
    }
}
