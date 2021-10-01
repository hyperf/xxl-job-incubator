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

use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Psr\Container\ContainerInterface;

abstract class AbstractGlueHandler implements GlueHandlerInterface
{
    protected ContainerInterface $container;

    protected JobHandlerManager $jobHandlerManager;

    protected ApiRequest $apiRequest;

    protected JobExecutorLoggerInterface $jobExecutorLogger;

    public function __construct(ContainerInterface $container, JobHandlerManager $jobHandlerManager, ApiRequest $apiRequest, JobExecutorLoggerInterface $jobExecutorLogger)
    {
        $this->container = $container;
        $this->jobHandlerManager = $jobHandlerManager;
        $this->apiRequest = $apiRequest;
        $this->jobExecutorLogger = $jobExecutorLogger;
    }
}
