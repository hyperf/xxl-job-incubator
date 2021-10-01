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
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Server\ServerInterface;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Dispatcher\XxlJobRoute;
use Hyperf\XxlJob\Handler\JobHandlerInterface;
use Hyperf\XxlJob\JobHandlerDefinition;
use Hyperf\XxlJob\JobHandlerManager;
use Psr\Container\ContainerInterface;

class BootAppRouteListener implements ListenerInterface
{
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
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * @throws \Exception
     */
    public function process(object $event)
    {
        if (! $this->xxlConfig->isEnable()) {
            return;
        }
        $prefixUrl = $this->xxlConfig->getExecutorServerPrefixUrl();
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

        if (! empty($prefixUrl)) {
            $prefixUrl = trim($prefixUrl, '/') . '/';
        } else {
            $prefixUrl = '';
        }
        $this->xxlJobRoute->add($httpServerRouter, $prefixUrl);

        $host = $serverConfig['host'];
        if (in_array($host, ['0.0.0.0', 'localhost'])) {
            $host = $this->getIp();
        }

        $url = sprintf('http://%s:%s/%s', $host, $serverConfig['port'], $prefixUrl);
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
                $this->jobHandlerManager->registerJobHandler($annotation->value, new JobHandlerDefinition($method['class'], $method['method'], $annotation->init, $annotation->destroy));
            }
        }

        $classes = AnnotationCollector::getClassesByAnnotation(XxlJob::class);
        foreach ($classes as $className => $annotation) {
            $classObj = $this->container->get($className);
            if (! $classObj instanceof JobHandlerInterface) {
                throw new Exception(sprintf('The %s job should be implement the %s interface', $className, JobHandlerInterface::class));
            }
            if ($annotation instanceof XxlJob) {
                $this->jobHandlerManager->registerJobHandler($annotation->value, new JobHandlerDefinition($className, 'execute', 'init', 'destroy'));
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function getIp(): string
    {
        $ips = swoole_get_local_ip();
        if (is_array($ips) && ! empty($ips)) {
            return current($ips);
        }
        /** @var mixed|string $ip */
        $ip = gethostbyname(gethostname());
        if (is_string($ip)) {
            return $ip;
        }
        throw new Exception('Can not get the internal IP.');
    }
}
