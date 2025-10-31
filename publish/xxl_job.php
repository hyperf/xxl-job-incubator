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

use function Hyperf\Support\env;

return [
    'enable' => env('XXL_JOB_ENABLE', true),
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8080/xxl-job-admin'),
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', ''),
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    'log_retention_days' => 30,
    'executor_server' => [
        // executor host (no Settings, automatically obtained)
        'host' => env('XXL_JOB_EXECUTOR_HOST'),
        // executor port
        'port' => env('XXL_JOB_EXECUTOR_PORT'),
        // executor prefix
        'prefix_url' => env('XXL_JOB_EXECUTOR_PREFIX_URL', 'php-xxl-job'),
    ],
    // execution mode: process or coroutine
    'execution_mode' => '',
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
