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
use InvalidArgumentException;
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
        // env() 返回字符串，'false'/'0' 需要按布尔归一化
        $instance->setGlueEnabled(filter_var($config->get('xxl_job.glue_enabled') ?? true, FILTER_VALIDATE_BOOLEAN));
        $adminAddress = trim((string) ($config->get('xxl_job.admin_address') ?? ''));
        if ($adminAddress === '') {
            $adminAddress = 'http://127.0.0.1:8080/xxl-job-admin';
        }
        $adminAddressArr = parse_url($adminAddress);
        if ($adminAddressArr === false || empty($adminAddressArr['scheme']) || empty($adminAddressArr['host'])) {
            // 启用时配置错误必须尽早暴露；禁用时不因无关配置阻断整个应用启动
            if ($instance->isEnable()) {
                throw new InvalidArgumentException(sprintf(
                    'xxl_job.admin_address "%s" is invalid, expected an absolute URL like http://127.0.0.1:8080/xxl-job-admin',
                    $adminAddress
                ));
            }
            $adminAddressArr = parse_url('http://127.0.0.1:8080/xxl-job-admin');
        }
        $scheme = strtolower($adminAddressArr['scheme']);
        $instance->setBaseUri(sprintf('%s://%s:%s', $scheme, $adminAddressArr['host'], $adminAddressArr['port'] ?? ($scheme === 'https' ? 443 : 80)));
        // 去掉尾斜杠：ApiRequest 会直接拼接 '/api/xxx'，保留 '/' 会拼出 '//api/xxx'，被 Guzzle 当成 network-path 引用解析到错误 host
        $instance->setServerUrlPath(rtrim($adminAddressArr['path'] ?? '', '/'));
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
