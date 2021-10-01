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

use Hyperf\XxlJob\JobContext;

class JobExecutorStdoutLogger extends AbstractLogger implements JobExecutorLoggerInterface
{
    public function log($level, $message, array $context = [])
    {
        if (! JobContext::hasJobLogId()) {
            return;
        }
        $config = $this->config->get(JobExecutorLoggerInterface::class, ['log_level' => []]);
        if (! in_array($level, $config['log_level'], true)) {
            return;
        }
        $jobIdMessage = 'XXL-JOB-LOG-ID: ' . JobContext::getJobLogId() . ' ';
        $message = $jobIdMessage . $message;
        $keys = array_keys($context);
        $tags = [];
        foreach ($keys as $k => $key) {
            if (in_array($key, $this->tags, true)) {
                $tags[$key] = $context[$key];
                unset($keys[$k]);
            }
        }
        $search = array_map(function ($key) {
            return sprintf('{%s}', $key);
        }, $keys);
        $message = str_replace($search, $context, $this->getMessage((string) $message, $level, $tags));

        $this->output->writeln($message);
    }

    public function retrieveLog(int $logId, int $logDateTime, int $fromLineNum, int $lineLimit): array
    {
        return [sprintf('%s does not supports retrieve log', static::class), 0, true];
    }
}
