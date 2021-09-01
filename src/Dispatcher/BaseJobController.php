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

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Server\ServerFactory;
use Hyperf\Utils\Codec\Json;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Logger\XxlJobHelper;
use Hyperf\XxlJob\Logger\XxlJobLogger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseJobController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $success = [
        'code' => 200,
        'msg' => null,
    ];

    /**
     * @var array
     */
    protected $fail = [
        'code' => 500,
        'msg' => null,
    ];

    /**
     * @var ServerFactory
     */
    protected $serverFactory;

    private $xxlJobLogger;

    private $xxlJobHelper;

    public function __construct(ContainerInterface $container, XxlJobLogger $xxlJobLogger, XxlJobHelper $xxlJobHelper)
    {
        $this->container = $container;
        $this->xxlJobLogger = $xxlJobLogger;
        $this->xxlJobHelper = $xxlJobHelper;
        $this->app = $this->container->get(Application::class);
        $this->serverFactory = $container->get(ServerFactory::class);
    }

    public function getXxlJobHelper(): XxlJobHelper
    {
        return $this->xxlJobHelper;
    }

    public function getXxlJobLogger(): XxlJobLogger
    {
        return $this->xxlJobLogger;
    }

    /**
     * @return array
     */
    public function input()
    {
        return $this->container->get(ServerRequestInterface::class)->getParsedBody();
    }

    public function resultJson($data): ResponseInterface
    {
        $response = $this->container->get(ResponseInterface::class);
        return $response->withAddedHeader('content-type', 'application/json')->withBody(new SwooleStream(Json::encode($data)));
    }
}
