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
namespace Hyperf\XxlJob\Dispatcher;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Server\ServerFactory;
use Hyperf\Utils\Codec\Json;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Logger\XxlJobHelper;
use Hyperf\XxlJob\Logger\XxlJobLogger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseJobController
{
    protected ContainerInterface $container;

    protected Application $application;

    protected ServerFactory $serverFactory;

    private XxlJobLogger $xxlJobLogger;

    protected StdoutLoggerInterface $stdoutLogger;

    protected JobExecutorLoggerInterface $jobExecutorLogger;

    /**
     * @var XxlJobHelper
     */
    private $xxlJobHelper;

    public function __construct(ContainerInterface $container, Application $application, ServerFactory $serverFactory, XxlJobLogger $xxlJobLogger, StdoutLoggerInterface $stdoutLogger, JobExecutorLoggerInterface $jobExecutorLogger)
    {
        $this->container = $container;
        $this->xxlJobLogger = $xxlJobLogger;
        $this->application = $application;
        $this->serverFactory = $serverFactory;
        $this->stdoutLogger = $stdoutLogger;
        $this->jobExecutorLogger = $jobExecutorLogger;
        $this->xxlJobHelper = $container->get(XxlJobHelper::class);
    }

    public function getXxlJobLogger(): XxlJobLogger
    {
        return $this->xxlJobLogger;
    }

    /**
     * @return XxlJobHelper
     */
    public function getXxlJobHelper(): mixed
    {
        return $this->xxlJobHelper;
    }

    public function input(): array
    {
        return (array) $this->container->get(ServerRequestInterface::class)->getParsedBody();
    }

    protected function response($data): ResponseInterface
    {
        $response = $this->container->get(ResponseInterface::class);
        return $response->withAddedHeader('content-type', 'application/json')->withBody(new SwooleStream(Json::encode($data)));
    }

    protected function responseSuccess(?string $message = null): ResponseInterface
    {
        return $this->response([
            'code' => 200,
            'msg' => $message,
        ]);
    }

    protected function responseFail(?string $message = null): ResponseInterface
    {
        return $this->response([
            'code' => 500,
            'msg' => $message,
        ]);
    }

}
