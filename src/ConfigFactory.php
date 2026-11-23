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
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class ConfigFactory
{
    public function __invoke(ContainerInterface $container): Config
    {
        $config = $container->get(ConfigInterface::class);
        $instance = new Config();
        // env() 对数值/布尔字符串不做类型转换（如 XXL_JOB_HEARTBEAT=30 得到 '30'），这里统一归一化，
        // 避免 strict_types 下把字符串传给 int/bool 形参的 setter 时抛 TypeError 导致应用无法启动
        $instance->setEnable(filter_var($config->get('xxl_job.enable') ?? false, FILTER_VALIDATE_BOOLEAN));
        $instance->setAppName($config->get('xxl_job.app_name') ?? '');
        $instance->setAccessToken($config->get('xxl_job.access_token') ?? '');
        if (! $instance->getAccessToken()) {
            $container->get(StdoutLoggerInterface::class)
                ->debug('xxl_job.access_token is not configured. All executor requests will be rejected.');
        }
        $adminAddressArr = parse_url($config->get('xxl_job.admin_address') ?? 'http://127.0.0.1:8080/xxl-job-admin');
        $instance->setBaseUri(sprintf('%s://%s:%s', $adminAddressArr['scheme'], $adminAddressArr['host'], $adminAddressArr['port'] ?? ($adminAddressArr['scheme'] == 'https' ? 443 : 80)));
        $instance->setServerUrlPath($adminAddressArr['path'] ?? '');
        $instance->setHeartbeat((int) ($config->get('xxl_job.heartbeat') ?? 30));
        $instance->setExecutorServerHost($config->get('xxl_job.executor_server.host') ?? '');
        $instance->setExecutorServerPort(intval($config->get('xxl_job.executor_server.port') ?? 9501));
        $instance->setExecutorServerPrefixUrl($config->get('xxl_job.executor_server.prefix_url') ?? '');
        $instance->setExecutionMode($config->get('xxl_job.execution_mode') ?? '');
        if ($config->has('xxl_job.guzzle.config') && ! empty($config->get('xxl_job.guzzle.config'))) {
            $instance->setGuzzleConfig($config->get('xxl_job.guzzle.config'));
        }
        $instance->setLogRetentionDays((int) ($config->get('xxl_job.log_retention_days') ?? -1));
        $instance->setMaxProcessLifetime((int) ($config->get('xxl_job.max_process_lifetime') ?? -1));

        $phpBinaryFinder = new PhpExecutableFinder();
        $phpBinaryPath = $phpBinaryFinder->find();

        $instance->setStartCommand($config->get('xxl_job.start_command') ?? [$phpBinaryPath, BASE_PATH . '/bin/hyperf.php']);

        $logFileDir = $config->get('xxl_job.file_logger.dir');
        if (! $logFileDir) {
            $logFileDir = BASE_PATH . '/runtime/xxl_job/logs/';
        }
        if (! is_dir($logFileDir)) {
            mkdir($logFileDir, 0755, true);
        }
        $instance->setLogFileDir($logFileDir);

        return $instance;
    }
}
