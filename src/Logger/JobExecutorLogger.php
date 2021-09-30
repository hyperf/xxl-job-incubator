<?php

namespace Hyperf\XxlJob\Logger;


use Hyperf\Framework\Logger\StdoutLogger;
use Hyperf\Utils\Context;

class JobExecutorLogger extends StdoutLogger implements JobExecutorLoggerInterface
{

    public function log($level, $message, array $context = [])
    {
        if (! Context::has(XxlJobLogger::MARK_JOB_LOG_ID)) {
            return;
        }
        $config = $this->config->get(JobExecutorLoggerInterface::class, ['log_level' => []]);
        if (! in_array($level, $config['log_level'], true)) {
            return;
        }
        $jobIdMessage = 'XXL-JOB-ID:' . Context::get(XxlJobLogger::MARK_JOB_LOG_ID) . ' ';
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

}