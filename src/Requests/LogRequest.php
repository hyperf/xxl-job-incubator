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

namespace Hyperf\XxlJob\Requests;

class LogRequest extends BaseRequest
{
    protected int $logDateTim = 0;

    protected int $logId = 0;

    protected int $fromLineNum = 1;

    public function getLogDateTim(): int
    {
        return $this->logDateTim;
    }

    public function getLogId(): int
    {
        return $this->logId;
    }

    public function getFromLineNum(): int
    {
        return $this->fromLineNum;
    }
}
