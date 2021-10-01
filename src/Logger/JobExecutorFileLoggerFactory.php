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

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class JobExecutorFileLoggerFactory
{
    public function __invoke(ContainerInterface $container): JobExecutorFileLogger
    {
        $config = $container->get(ConfigInterface::class);
        $logFileDir = $config->get('xxl_job.file_logger.dir');
        if (! $logFileDir) {
            $logFileDir = BASE_PATH . '/runtime/xxl_job/logs/';
        }
        $instance = new JobExecutorFileLogger($config, null);
        $instance->init($logFileDir);
        return $instance;
    }
}
