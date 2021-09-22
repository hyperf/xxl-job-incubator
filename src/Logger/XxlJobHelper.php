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

    public function log($message, ...$param)
    {
        if (empty(Context::get(XxlJobLogger::MARK_JOB_LOG_ID))) {
            return;
        }
        if (! empty($param)) {
            $message = sprintf($message, ...$param);
        }
        $this->xxlJobLogger->get()->info($message);
    }

    public function get(): ?LoggerInterface
    {
        if (empty(Context::get(XxlJobLogger::MARK_JOB_LOG_ID))) {
            return null;
        }
        return $this->xxlJobLogger->get();
    }

    public function logFile(): string
    {
        return $this->xxlJobLogger->getStream()->getTimedFilename();
    }

    public function getRunRequest(): RunRequest
    {
        return Context::get(RunRequest::class);
    }

    public function getJobParam(): string
    {
        return self::getRunRequest()->getExecutorParams();
    }
}
