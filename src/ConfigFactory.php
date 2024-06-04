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
namespace Hyperf\XxlJob;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ConfigFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $instance = new Config();
        $instance->setEnable($config->get('xxl_job.enable') ?? false);
        $instance->setAppName($config->get('xxl_job.app_name') ?? '');
        $instance->setAccessToken($config->get('xxl_job.access_token') ?? '');
        $adminAddressArr = parse_url($config->get('xxl_job.admin_address') ?? 'http://127.0.0.1:8080/xxl-job-admin');
        $instance->setBaseUri(sprintf('%s://%s:%s', $adminAddressArr['scheme'], $adminAddressArr['host'], $adminAddressArr['port'] ?? ($adminAddressArr['scheme'] == 'https' ? 443 : 80)));
        $instance->setServerUrlPath($adminAddressArr['path'] ?? '');
        $instance->setHeartbeat(intval($config->get('xxl_job.heartbeat') ?? 30));
        $instance->setExecutorServerPrefixUrl($config->get('xxl_job.executor_server.prefix_url') ?? '');
        if ($config->has('xxl_job.guzzle.config') && !empty($config->get('xxl_job.guzzle.config'))) {
            $instance->setGuzzleConfig($config->get('xxl_job.guzzle.config'));
        }
        $instance->setLogRetentionDays($config->get('xxl_job.log_retention_days') ?? -1);
        $instance->setStartCommand($config->get('xxl_job.start_command') ?? [PHP_BINARY, BASE_PATH . '/bin/hyperf.php']);

        $logFileDir = $config->get('xxl_job.file_logger.dir');
        if (!$logFileDir) {
            $logFileDir = BASE_PATH . '/runtime/xxl_job/logs/';
        }
        if (!is_dir($logFileDir)) {
            mkdir($logFileDir, 0777, true);
        }
        $instance->setLogFileDir($logFileDir);

        return $instance;
    }
}
