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

use Hyperf\XxlJob\Requests\RunRequest;

class ProcessTitle
{
    public const PROCESS_PREFIX_TITLE = 'hyperf:xxl-job';

    public static function setByArgv(array $argv)
    {
        if ($argv[1] != 'execute:xxl-job') {
            return;
        }
        $runArr = json_decode($argv[3], true);
        $runRequest = RunRequest::create($runArr);

        static::setByRunRequest($runRequest);
    }

    public static function setByRunRequest(RunRequest $runRequest)
    {
        $process_title = static::generate($runRequest, getmypid());
        cli_set_process_title($process_title);
    }

    protected static function generate(RunRequest $runRequest, int $pid): string
    {
        return sprintf(self::PROCESS_PREFIX_TITLE . '_%s_%s_%s_%s_end', $runRequest->getJobId(), $runRequest->getLogId(), $runRequest->getLogDateTime(), $pid);
    }
}
