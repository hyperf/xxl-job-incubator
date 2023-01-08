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

use Hyperf\Context\Context;
use Hyperf\Engine\Coroutine as Co;
use Hyperf\XxlJob\Requests\RunRequest;

class JobContext extends Context
{
    public const JOB_LOG_ID_KEY = 'XXL-JOB-LOG-ID';

    public static function getAll(): ?\ArrayObject
    {
        return Co::getContextFor();
    }

    public static function getJobLogId(): ?int
    {
        return Context::get(self::JOB_LOG_ID_KEY);
    }

    public static function hasJobLogId(): bool
    {
        return Context::has(self::JOB_LOG_ID_KEY);
    }

    public static function setJobLogId(?int $logId): void
    {
        Context::set(self::JOB_LOG_ID_KEY, $logId);
    }

    public static function getRunRequest(): ?RunRequest
    {
        return Context::get(RunRequest::class);
    }

    public static function setRunRequest(RunRequest $request): void
    {
        Context::set(RunRequest::class, $request);
    }
}
