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

use Hyperf\XxlJob\Listener\BootAppRouteListener;
use Hyperf\XxlJob\Listener\MainWorkerStartListener;
use Hyperf\XxlJob\Listener\OnShutdownListener;
use Hyperf\XxlJob\Logger\JobExecutorLogger;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Psr\Log\LogLevel;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                Application::class => ApplicationFactory::class,
                JobExecutorLoggerInterface::class => JobExecutorLogger::class,
            ],
            'listeners' => [
                BootAppRouteListener::class,
                MainWorkerStartListener::class,
                OnShutdownListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for xxl_job.',
                    'source' => __DIR__ . '/../publish/xxl_job.php',
                    'destination' => BASE_PATH . '/config/autoload/xxl_job.php',
                ],
            ],
            JobExecutorLoggerInterface::class => [
                'log_level' => [
                    LogLevel::ALERT,
                    LogLevel::CRITICAL,
                    LogLevel::DEBUG,
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                    LogLevel::INFO,
                    LogLevel::NOTICE,
                    LogLevel::WARNING,
                ],
            ],
        ];
    }
}
