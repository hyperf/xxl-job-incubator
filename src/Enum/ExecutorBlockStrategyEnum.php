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
namespace Hyperf\XxlJob\Enum;

class ExecutorBlockStrategyEnum
{
    public const SERIAL_EXECUTION = 'SERIAL_EXECUTION';

    public const DISCARD_LATER = 'DISCARD_LATER';

    public const COVER_EARLY = 'COVER_EARLY';
}
