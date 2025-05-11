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

use Hyperf\Codec\Json;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Service\JobService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseController
{
    public function __construct(
        protected ContainerInterface $container,
        protected StdoutLoggerInterface $stdoutLogger,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected JobService $jobService,
    ) {
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
