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

use Hyperf\Process\AbstractProcess;
use Hyperf\Process\ProcessManager;
use Hyperf\XxlJob\Config;
use Psr\Container\ContainerInterface;

class JobDispatcherProcess extends AbstractProcess
{
    public const JOB_DISPATCHER_NAME = 'xxj-job-dispatcher';

    public $name = self::JOB_DISPATCHER_NAME;

    public $nums = 1;

    protected Config $xxlConfig;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->xxlConfig = $container->get(Config::class);
    }

    public function isEnable($server): bool
    {
        return $this->xxlConfig->isEnable();
    }

    public function handle(): void
    {
        while (ProcessManager::isRunning()) {
            sleep(1);
        }
    }
}
