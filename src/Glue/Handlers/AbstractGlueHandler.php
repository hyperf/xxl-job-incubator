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
namespace Hyperf\XxlJob\Glue\Handlers;

use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\JobRun;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Psr\Container\ContainerInterface;

abstract class AbstractGlueHandler implements GlueHandlerInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected JobHandlerManager $jobHandlerManager,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected JobRun $jobRun
    ) {
    }
}
