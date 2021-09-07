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
    private static $xxlJobLogger;

    public function __construct(XxlJobLogger $xxlJobLogger)
    {
        self::$xxlJobLogger = $xxlJobLogger;
    }

    public static function log($message)
    {
        if (empty(Context::get(XxlJobLogger::MARK_JOB_LOG_ID))) {
            return;
        }
        self::$xxlJobLogger->get()->info($message);
    }

    public static function get(): ?LoggerInterface
    {
        if (empty(Context::get(XxlJobLogger::MARK_JOB_LOG_ID))) {
            return null;
        }
        return self::$xxlJobLogger->get();
    }

    public static function logFile(): string
    {
        return self::$xxlJobLogger->getStream()->getTimedFilename();
    }

    public static function getRunRequest(): RunRequest
    {
        return Context::get(RunRequest::class);
    }

    public static function getJobParam(): string
    {
        return self::getRunRequest()->getExecutorParams();
    }
}
