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
        $configToken = $this->xxlConfig->getAccessToken();
        if (! $configToken) {
            return $handler->handle($request);
        }

        $token = $request->getHeaderLine('xxl-job-access-token') ?? '';
        if ($token !== $configToken) {
            $response = $this->container->get(HttpResponse::class);
            return $response->json([
                'code' => 401,
                'msg' => 'Invalid Access Token',
            ])->withStatus(401);
        }

        JobContext::setJobLogId($request->getParsedBody()['logId'] ?? null);

        return $handler->handle($request);
    }
}
