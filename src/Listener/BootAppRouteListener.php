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
use Hyperf\XxlJob\Annotation\JobHandler;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Dispatcher\XxlJobRoute;
use Psr\Container\ContainerInterface;

class BootAppRouteListener implements ListenerInterface
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container, Application $app)
    {
        $this->container = $container;
        $this->app = $app;
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event)
    {
        $logger = $this->container->get(StdoutLoggerInterface::class);
        $config = $this->container->get(ConfigInterface::class);
        if (! $config->get('xxl_job.enable', false)) {
            $logger->debug('xxl_job not enable');
            return;
        }
        $prefixUrl = $config->get('xxl_job.prefix_url', 'php-xxl-job');
        $servers = $config->get('server.servers');
        $httpServerRouter = null;
        $serverConfig = null;
        foreach ($servers as $server) {
            $router = $this->container->get(DispatcherFactory::class)->getRouter($server['name']);
            if (empty($httpServerRouter) && $server['type'] == ServerInterface::SERVER_HTTP) {
                $httpServerRouter = $router;
                $serverConfig = $server;
            }
        }
        if (empty($httpServerRouter)) {
            $logger->warning('XxlJob: http Service not started');
            $this->app->getConfig()->setEnable(false);
            return;
        }
        $this->initAnnotationRoute();

        $route = new XxlJobRoute();
        if(!empty($prefixUrl)){
            $prefixUrl = trim($prefixUrl,'/').'/';
        }else{
            $prefixUrl = '';
        }
        $route->add($httpServerRouter, $prefixUrl);

        $host = $serverConfig['host'];
        if (in_array($host, ['0.0.0.0', 'localhost'])) {
            $host = $this->getIp();
        }

        $url = sprintf('http://%s:%s/%s', $host, $serverConfig['port'], $prefixUrl);
        $this->app->getConfig()->setClientUrl($url);
    }

    private function initAnnotationRoute(): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(JobHandler::class);
        /**
         * @var string $className
         * @var JobHandler $annotation
         */
        foreach ($classes as $className => $annotation) {
            Application::setJobHandlers($annotation->value, $className);
        }
    }

    /**
     * @throws Exception
     */
    private function getIp(): string
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
