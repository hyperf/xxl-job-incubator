<?php

namespace Hyperf\XxlJob;


use Hyperf\Utils\Context;
use Hyperf\Utils\Coroutine;
use Hyperf\XxlJob\Logger\XxlJobLogger;
use Hyperf\XxlJob\Requests\RunRequest;

class JobContext extends Context
{

    public static function runJob(JobDefinition $jobDefinition, RunRequest $request, callable $callback)
    {
        return Coroutine::create(function () use ($request, $callback, $jobDefinition) {
            Context::set(XxlJobLogger::MARK_JOB_LOG_ID, $request->getLogId());
            Context::set(RunRequest::class, $request);
            return $callback($jobDefinition, $request);
        });
    }

    public static function getRunRequest(): ?RunRequest
    {
        return Context::get(RunRequest::class);
    }

}