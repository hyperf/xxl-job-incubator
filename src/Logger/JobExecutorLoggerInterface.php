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
namespace Hyperf\XxlJob\Logger;

interface JobExecutorLoggerInterface extends XxlJobLoggerInterface
{
    public function retrieveLog(int $logId, int $logDateTime, int $fromLineNum, int $lineLimit): LogContent;
}
