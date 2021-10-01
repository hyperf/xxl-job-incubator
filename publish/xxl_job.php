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
return [
    'enable' => env('XXL_JOB_ENABLE', true),
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8769/xxl-job-admin'),
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', ''),
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    'executor_server' => [
        'prefix_url' => env('XXL_JOB_EXECUTOR_PREFIX_URL', 'php-xxl-job')
    ],
    'guzzle_config' => [
        'headers' => [
            'charset' => 'UTF-8',
        ],
        'timeout' => 10,
    ],
];
