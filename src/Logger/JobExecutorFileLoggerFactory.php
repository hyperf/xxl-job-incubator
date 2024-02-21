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
use Hyperf\XxlJob\Config;
use Psr\Container\ContainerInterface;

class JobExecutorFileLoggerFactory
{
    public function __invoke(ContainerInterface $container): JobExecutorFileLogger
    {
        $config = $container->get(ConfigInterface::class);
        $xxlConfig = $container->get(Config::class);
        $instance = new JobExecutorFileLogger($config, null);
        $instance->init($xxlConfig->getLogFileDir());
        return $instance;
    }
}
