<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Event;

use Hyperf\XxlJob\Requests\RunRequest;

class BeforeJobRun
{
    public function __construct(public RunRequest $request)
    {
    }
}
