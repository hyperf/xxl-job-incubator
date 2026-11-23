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

namespace Hyperf\XxlJob\Process;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\ProcessManager;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Psr\Container\ContainerInterface;
use Throwable;

class JobDispatcherProcess extends AbstractProcess
{
    public const JOB_DISPATCHER_NAME = 'xxl-job-dispatcher';

    public const RECV_LENGTH = 65535;

    public string $name = self::JOB_DISPATCHER_NAME;

    public int $nums = 1;

    protected int $recvLength = self::RECV_LENGTH;

    protected Config $xxlConfig;

    protected StdoutLoggerInterface $stdoutLogger;

    protected int $startedAt = 0;

    protected JobSerialExecutionService $jobSerialExecutionService;

    protected int $shutdownTimeout = 30;

    private static bool $acceptingJobs = true;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->xxlConfig = $container->get(Config::class);
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
        $this->jobSerialExecutionService = $container->get(JobSerialExecutionService::class);
    }

    public function isEnable($server): bool
    {
        return $this->xxlConfig->isEnable();
    }

    public static function setAcceptingJobs(bool $accepting): void
    {
        self::$acceptingJobs = $accepting;
    }

    public static function isAcceptingJobs(): bool
    {
        return self::$acceptingJobs;
    }

    public function handle(): void
    {
        self::$acceptingJobs = true;
        $this->startedAt = time();
        $maxLifetime = $this->xxlConfig->getMaxProcessLifetime();

        $this->stdoutLogger->info(sprintf(
            'XXL-JOB dispatcher process started, max lifetime: %d seconds (%s restart)',
            $maxLifetime,
            $maxLifetime > 0 ? $this->formatDuration($maxLifetime) : 'never'
        ));

        // Keep original behavior when auto-restart is disabled
        if ($maxLifetime <= 0) {
            while (ProcessManager::isRunning()) {
                sleep(1);
            }
            return;
        }

        while (ProcessManager::isRunning()) {
            sleep(1);

            $elapsed = time() - $this->startedAt;
            if ($elapsed < $maxLifetime) {
                continue;
            }

            self::$acceptingJobs = false;
            $this->stdoutLogger->info(sprintf(
                'XXL-JOB dispatcher process ran %s, reached max lifetime, shutting down',
                $this->formatDuration($elapsed),
            ));

            $shutdownStartedAt = time();
            while (ProcessManager::isRunning()) {
                if (! $this->jobSerialExecutionService->hasPendingJobs()) {
                    $this->stdoutLogger->info('XXL-JOB dispatcher process: no running or queued jobs, exiting safely');
                    return;
                }
                if (time() - $shutdownStartedAt >= $this->shutdownTimeout) {
                    $this->stdoutLogger->warning('XXL-JOB dispatcher graceful shutdown timed out, failing unfinished jobs');
                    try {
                        // 超时退出前必须告知 admin，不能让运行中和排队日志永久停在 running
                        $this->jobSerialExecutionService->failPendingJobs('XXL-JOB dispatcher exceeded graceful shutdown timeout; job not completed.');
                    } catch (Throwable $throwable) {
                        $this->stdoutLogger->error($throwable->getMessage());
                    }
                    return;
                }
                sleep(1);
            }

            return;
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'min';
        }
        return intdiv($seconds, 3600) . 'h';
    }
}
