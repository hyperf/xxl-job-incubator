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

use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\JobContext;

class JobExecutorFileLogger extends AbstractLogger implements JobExecutorLoggerInterface
{
    protected string $logFileDir;

    public function init(string $logFileDir)
    {
        $this->logFileDir = $logFileDir;
    }

    public function retrieveLog(int $logId, int $logDateTime, int $fromLineNum, int $lineLimit): LogContent
    {
        $filePath = $this->getLogFileFullPath($logId);
        if (! file_exists($filePath)) {
            return new LogContent('log file does not exist', 1, true);
        }
        $log = new JobLogFileObject($filePath);
        if (! $log->isReadable()) {
            throw new XxlJobException(sprintf('XXL-JOB log file %s is not exists or is not readable', $filePath));
        }
        return $log->getContent($fromLineNum - 1, $lineLimit);
    }

    public function log($level, $message, array $context = []): void
    {
        $config = $this->config->get(JobExecutorLoggerInterface::class, ['log_level' => []]);
        if (! in_array($level, $config['log_level'], true)) {
            return;
        }
        $logId = (int) ($context['log_id'] ?? JobContext::getJobLogId());
        $logFilePath = $this->getLogFileFullPath($logId);
        $dateFormat = 'Y-m-d H:i:s';
        $message = sprintf('%s [%s]: %s' . PHP_EOL, date($dateFormat), strtoupper($level), $message);
        file_put_contents($logFilePath, $message, FILE_APPEND);
    }

    public function getLogFileFullPath(int $logId): string
    {
        return $this->logFileDir . $this->generateFileName($logId);
    }

    public function generateFileName(int $logId): string
    {
        return $logId . '.log';
    }
}
