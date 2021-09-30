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
namespace Hyperf\XxlJob\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnShutdown;
use Hyperf\Server\Event\CoroutineServerStop;
use Hyperf\XxlJob\Application;
use Psr\Container\ContainerInterface;

class OnShutdownListener implements ListenerInterface
{
    protected ContainerInterface $container;

    protected StdoutLoggerInterface $logger;

    protected bool $processed = false;

    protected Application $application;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->application = $container->get(Application::class);
    }

    public function listen(): array
    {
        return [
            OnShutdown::class,
            CoroutineServerStop::class,
        ];
    }

    public function process(object $event)
    {
        if ($this->processed) {
            return;
        }
        $this->processed = true;

        $config = $this->application->getConfig();
        if (! $config->isEnable()) {
            return;
        }
        $response = $this->application->service->registryRemove($config->getAppName(), $config->getClientUrl());
        if ($response->getStatusCode() === 200) {
            $this->logger->debug(sprintf('Remove the XXL-JOB app name: %s url:%s is successful', $config->getAppName(), $config->getClientUrl()));
        } else {
            $this->logger->error(sprintf('Failed to remove the XXL-JOB app name:%s url:%s', $config->getAppName(), $config->getClientUrl()));
        }
    }
}
