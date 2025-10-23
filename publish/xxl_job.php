<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // 是否启动 xxl-job
    'enable' => env('XXL_JOB_ENABLE', true),
    // xxl-job admin 地址
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8080/xxl-job-admin'),
    // xxl-job app_name
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    // xxl-job access_token
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', ''),
    // xxl-job 心跳时间
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    // xxl-job 日志保留天数
    'log_retention_days' => 30,
    // xxl-job 执行器配置
    'executor_server' => [
        // 执行器地址
        'host' => env('XXL_JOB_EXECUTOR_HOST', '127.0.0.1'),
        // 执行器端口
        'port' => intval(env('XXL_JOB_EXECUTOR_PORT', 9501)),
        // 执行器前缀
        'prefix_url' => env('XXL_JOB_EXECUTOR_PREFIX_URL', 'php-xxl-job')
    ],
    'guzzle_config' => [
        'headers' => [
            'charset' => 'UTF-8',
        ],
        'timeout' => 10,
    ],
    'file_logger' => [
        'dir' => BASE_PATH . '/runtime/xxl_job/logs/',
    ],
];
