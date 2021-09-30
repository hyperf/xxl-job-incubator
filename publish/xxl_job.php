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
    'prefix_url' => env('XXL_JOB_PREFIX_URL', 'php-xxl-job'),
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', null),
];
