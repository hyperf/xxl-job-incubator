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
namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Handler\AbstractJobHandler;
use Hyperf\XxlJob\Requests\RunRequest;

class BarJobClass extends AbstractJobHandler
{
    public function execute(RunRequest $request): void
    {
    }
}
