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
            return $this->deny(500, 'The access token is not configured. Please configure xxl_job.access_token.');
        }

        // getHeaderLine 对头名大小写不敏感，这里与官方头名保持一致
        if (! hash_equals($configToken, $request->getHeaderLine('XXL-JOB-ACCESS-TOKEN'))) {
            return $this->deny(401, 'Invalid Access Token');
        }

        // 3.5.0 起 AccessToken 为执行器组维度，admin 依赖 appname 定位 token，缺失或匹配失败一律拒绝
        $appName = trim($request->getHeaderLine('XXL-JOB-APPNAME'));
        if ($appName === '') {
            return $this->deny(401, 'Missing XXL-JOB-APPNAME header');
        }
        $configAppName = $this->xxlConfig->getAppName();
        if ($configAppName === '') {
            return $this->deny(500, 'The app name is not configured. Please configure xxl_job.app_name.');
        }
        if (! hash_equals($configAppName, $appName)) {
            return $this->deny(401, 'Invalid App Name');
        }

        JobContext::setJobLogId($request->getParsedBody()['logId'] ?? null);

        return $handler->handle($request);
    }

    protected function deny(int $status, string $message): ResponseInterface
    {
        return $this->container->get(HttpResponse::class)
            ->json(['code' => $status, 'msg' => $message])
            ->withStatus($status);
    }
}
