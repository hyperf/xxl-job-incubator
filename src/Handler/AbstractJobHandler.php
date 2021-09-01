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
namespace Hyperf\XxlJob\Handler;

use Hyperf\XxlJob\Logger\XxlJobHelper;
use Hyperf\XxlJob\Requests\RunRequest;

abstract class AbstractJobHandler implements JobHandlerInterface
{
    private $runRequest;

    private $xxlJobHelper;

    public function __construct(XxlJobHelper $xxlJobHelper)
    {
        $this->xxlJobHelper = $xxlJobHelper;
    }

    public function getXxlJobHelper(): XxlJobHelper
    {
        return $this->xxlJobHelper;
    }

    public function getRunRequest(): RunRequest
    {
        return $this->runRequest;
    }

    public function getParams(): string
    {
        return $this->runRequest->getExecutorParams();
    }

    public function setRunRequest(RunRequest $runRequest): void
    {
        $this->runRequest = $runRequest;
    }
}
