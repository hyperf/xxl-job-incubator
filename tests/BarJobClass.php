<?php

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Handler\AbstractJobHandler;
use Hyperf\XxlJob\Requests\RunRequest;

class BarJobClass extends AbstractJobHandler
{

    public function execute(RunRequest $request): void
    {
    }
}