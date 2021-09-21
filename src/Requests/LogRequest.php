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
    protected int $logDateTim;

    protected int $logId;

    protected int $fromLineNum;

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
