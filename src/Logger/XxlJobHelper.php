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

use Hyperf\Utils\Context;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Log\LoggerInterface;

class XxlJobHelper
{
    /**
     * @var XxlJobLogger
     */
    private $xxlJobLogger;

    public function __construct(XxlJobLogger $xxlJobLogger)
    {
        $this->xxlJobLogger = $xxlJobLogger;
    }

    public function getLogFilename(): string
    {
        return $this->xxlJobLogger->getStream()->getTimedFilename();
    }

}
