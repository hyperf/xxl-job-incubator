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
namespace Hyperf\XxlJob\Middleware;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\JobContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    protected Config $xxlConfig;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, Config $xxlConfig)
    {
        $this->xxlConfig = $xxlConfig;
        $this->container = $container;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaders()['xxl-job-access-token'][0] ?? '';
        if ($token != $this->xxlConfig->getAccessToken()) {
            $response = $this->container->get(HttpResponse::class);
            $json = json_encode([
                'code' => 401,
                'msg' => 'Invalid Token',
            ], JSON_UNESCAPED_UNICODE);
            return $response->withStatus(401)
                ->withAddedHeader('content-type', 'application/json; charset=utf-8')
                ->withBody(new SwooleStream($json));
        }

        JobContext::setJobLogId($request->getParsedBody()['logId'] ?? null);

        return $handler->handle($request);
    }
}
