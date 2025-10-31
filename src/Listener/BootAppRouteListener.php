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

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\IPReaderInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Engine\Constant;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Server\ServerInterface;
use Hyperf\Support\Network;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Dispatcher\XxlJobRoute;
use Hyperf\XxlJob\Handler\JobHandlerInterface;
use Hyperf\XxlJob\JobHandlerDefinition;
use Hyperf\XxlJob\JobHandlerManager;
use Psr\Container\ContainerInterface;

class BootAppRouteListener implements ListenerInterface
{
    public static int $AppStartTime = 0;

    protected JobHandlerManager $jobHandlerManager;

    protected ContainerInterface $container;

    protected ConfigInterface $config;

    protected StdoutLoggerInterface $logger;

    protected DispatcherFactory $dispatcherFactory;

    protected XxlJobRoute $xxlJobRoute;

    protected Config $xxlConfig;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->jobHandlerManager = $container->get(JobHandlerManager::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->dispatcherFactory = $container->get(DispatcherFactory::class);
        $this->xxlJobRoute = $container->get(XxlJobRoute::class);
        $this->xxlConfig = $container->get(Config::class);
        static::$AppStartTime = time();
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * @throws Exception
     */
    public function process(object $event): void
    {
        if (! $this->xxlConfig->isEnable()) {
            return;
        }
        $executorServerPrefixUrl = $this->xxlConfig->getExecutorServerPrefixUrl();
        $executorServerHost = $this->xxlConfig->getExecutorServerHost();
        $executorServerPort = $this->xxlConfig->getExecutorServerPort();

        $servers = $this->config->get('server.servers');
        $httpServerRouter = null;
        $serverConfig = null;
        foreach ($servers as $server) {
            $router = $this->dispatcherFactory->getRouter($server['name']);
            if (empty($httpServerRouter) && $server['type'] == ServerInterface::SERVER_HTTP) {
                $httpServerRouter = $router;
                $serverConfig = $server;
            }
        }
        if (empty($httpServerRouter)) {
            $this->logger->warning('XXL-JOB HTTP Service is not ready.');
            $this->xxlConfig->setEnable(false);
            return;
        }

        $this->initAnnotationRoute();

        if (! empty($executorServerPrefixUrl)) {
            $executorServerPrefixUrl = trim($executorServerPrefixUrl, '/') . '/';
        } else {
            $executorServerPrefixUrl = '';
        }
        $this->xxlJobRoute->add($httpServerRouter, $executorServerPrefixUrl);

        if (empty($executorServerHost)) {
            if ($this->container->has(IPReaderInterface::class)) {
                $executorServerHost = $this->container->get(IPReaderInterface::class)->read();
            } else {
                $executorServerHost = $serverConfig['host'];
                if (in_array($executorServerHost, ['0.0.0.0', 'localhost'])) {
                    $executorServerHost = Network::ip();
                }
            }
            $executorServerPort = $serverConfig['port'];
        }

        $scheme = $executorServerPort == 443 ? 'https' : 'http';
        $url = sprintf('%s://%s:%s/%s', $scheme, $executorServerHost, $executorServerPort, $executorServerPrefixUrl);
        $this->xxlConfig->setClientUrl($url);
    }

    /**
     * @throws Exception
     */
    protected function initAnnotationRoute(): void
    {
        $methods = AnnotationCollector::getMethodsByAnnotation(XxlJob::class);

        foreach ($methods as $method) {
            $annotation = $method['annotation'];
            if ($annotation instanceof XxlJob) {
                $this->jobHandlerManager->registerJobHandler($annotation->value, new JobHandlerDefinition($method['class'], $method['method'], $annotation->init, $annotation->destroy, $this->getExecutionMode($annotation)));
            }
        }

        $classes = AnnotationCollector::getClassesByAnnotation(XxlJob::class);
        foreach ($classes as $className => $annotation) {
            $classObj = $this->container->get($className);
            if (! $classObj instanceof JobHandlerInterface) {
                throw new Exception(sprintf('The %s job should be implement the %s interface', $className, JobHandlerInterface::class));
            }
            if ($annotation instanceof XxlJob) {
                $this->jobHandlerManager->registerJobHandler($annotation->value, new JobHandlerDefinition($className, 'execute', 'init', 'destroy', $this->getExecutionMode($annotation)));
            }
        }
    }

    protected function getExecutionMode(XxlJob $xxlJob): string
    {
        $executionMode = $xxlJob->executionMode ?: $this->xxlConfig->getExecutionMode();
        if ($executionMode == XxlJob::PROCESS) {
            return XxlJob::PROCESS;
        }
        if ($executionMode == XxlJob::COROUTINE) {
            return XxlJob::COROUTINE;
        }
        if (Constant::ENGINE == 'Swoole') {
            return XxlJob::PROCESS;
        }
        return XxlJob::COROUTINE;
    }
}
