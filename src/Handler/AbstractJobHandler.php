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

use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\XxlJob\Logger\XxlJobHelper;
use Hyperf\XxlJob\Requests\RunRequest;

abstract class AbstractJobHandler implements JobHandlerInterface
{

    public function getXxlJobHelper(): XxlJobHelper
    {
        return ApplicationContext::getContainer()->get(XxlJobHelper::class);
    }

    public function getRunRequest(): RunRequest
    {
        return Context::get(RunRequest::class);
    }

    public function getParams(): string
    {
        return $this->getRunRequest()->getExecutorParams();
    }

}
