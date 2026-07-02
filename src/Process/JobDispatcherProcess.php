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
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Psr\Container\ContainerInterface;

class JobDispatcherProcess extends AbstractProcess
{
    public const JOB_DISPATCHER_NAME = 'xxl-job-dispatcher';

    public string $name = self::JOB_DISPATCHER_NAME;

    public int $nums = 1;

    protected Config $xxlConfig;

    protected StdoutLoggerInterface $stdoutLogger;

    protected int $startedAt = 0;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->xxlConfig = $container->get(Config::class);
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
    }

    public function isEnable($server): bool
    {
        return $this->xxlConfig->isEnable();
    }

    public function handle(): void
    {
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

            // === Enter graceful shutdown phase ===
            $this->stdoutLogger->info(sprintf(
                'XXL-JOB dispatcher process ran %s, reached max lifetime, shutting down',
                $this->formatDuration($elapsed),
            ));

            $waited = 0;
            while (ProcessManager::isRunning()) {
                $running = JobRunContent::getRunningJobIds();
                if (empty($running)) {
                    $this->stdoutLogger->info('XXL-JOB dispatcher process: no running jobs, exiting safely');
                    return;
                }

                sleep(1);
                ++$waited;
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
