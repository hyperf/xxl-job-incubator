<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob\Middleware;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Middleware\AuthMiddleware;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Middleware\AuthMiddleware
 */
class AuthMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    /**
     * 当未配置 access_token 时，应返回 500 拒绝请求（而非直接放行）.
     */
    public function testRejectsWhenTokenNotConfigured(): void
    {
        $config = new Config();
        $config->setAccessToken('');

        $jsonResponse = m::mock(ResponseInterface::class);
        $httpResponse = m::mock(HttpResponse::class);

        $container = m::mock(ContainerInterface::class);
        $request = m::mock(ServerRequestInterface::class);
        $handler = m::mock(RequestHandlerInterface::class);

        $httpResponse->shouldReceive('json')
            ->with(['code' => 500, 'msg' => 'The access token is not configured. Please configure xxl_job.access_token.'])
            ->once()
            ->andReturn($jsonResponse);

        $jsonResponse->shouldReceive('withStatus')
            ->with(500)
            ->once()
            ->andReturnSelf();

        $container->shouldReceive('get')
            ->with(HttpResponse::class)
            ->once()
            ->andReturn($httpResponse);

        // handler 不应被调用
        $handler->shouldNotReceive('handle');

        $middleware = new AuthMiddleware($container, $config);
        $result = $middleware->process($request, $handler);

        $this->assertSame($jsonResponse, $result);
    }

    /**
     * Token 匹配时应放行并注入 logId 到 JobContext.
     */
    public function testPassesWhenTokenMatches(): void
    {
        $config = new Config();
        $config->setAccessToken('secret-token');

        $container = m::mock(ContainerInterface::class);
        $request = m::mock(ServerRequestInterface::class);
        $handler = m::mock(RequestHandlerInterface::class);

        $request->shouldReceive('getHeaderLine')
            ->with('xxl-job-access-token')
            ->once()
            ->andReturn('secret-token');

        $request->shouldReceive('getParsedBody')
            ->once()
            ->andReturn(['logId' => 12345]);

        $handler->shouldReceive('handle')->with($request)->once()->andReturn(
            m::mock(ResponseInterface::class)
        );

        $middleware = new AuthMiddleware($container, $config);
        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * Token 不匹配时应返回 401.
     */
    public function testReturns401WhenTokenMismatch(): void
    {
        $config = new Config();
        $config->setAccessToken('secret-token');

        $jsonResponse = m::mock(ResponseInterface::class);
        $httpResponse = m::mock(HttpResponse::class);

        $container = m::mock(ContainerInterface::class);
        $request = m::mock(ServerRequestInterface::class);
        $handler = m::mock(RequestHandlerInterface::class);

        $request->shouldReceive('getHeaderLine')
            ->with('xxl-job-access-token')
            ->once()
            ->andReturn('wrong-token');

        $httpResponse->shouldReceive('json')
            ->with(['code' => 401, 'msg' => 'Invalid Access Token'])
            ->once()
            ->andReturn($jsonResponse);

        $jsonResponse->shouldReceive('withStatus')
            ->with(401)
            ->once()
            ->andReturnSelf();

        $container->shouldReceive('get')
            ->with(HttpResponse::class)
            ->once()
            ->andReturn($httpResponse);

        // handler 不应被调用
        $handler->shouldNotReceive('handle');

        $middleware = new AuthMiddleware($container, $config);
        $result = $middleware->process($request, $handler);

        $this->assertSame($jsonResponse, $result);
    }

    /**
     * 请求头中缺少 token 时返回 401.
     */
    public function testReturns401WhenTokenMissing(): void
    {
        $config = new Config();
        $config->setAccessToken('secret-token');

        $jsonResponse = m::mock(ResponseInterface::class);
        $httpResponse = m::mock(HttpResponse::class);

        $container = m::mock(ContainerInterface::class);
        $request = m::mock(ServerRequestInterface::class);
        $handler = m::mock(RequestHandlerInterface::class);

        $request->shouldReceive('getHeaderLine')
            ->with('xxl-job-access-token')
            ->once()
            ->andReturn('');

        $httpResponse->shouldReceive('json')
            ->with(['code' => 401, 'msg' => 'Invalid Access Token'])
            ->once()
            ->andReturn($jsonResponse);

        $jsonResponse->shouldReceive('withStatus')
            ->with(401)
            ->once()
            ->andReturnSelf();

        $container->shouldReceive('get')
            ->with(HttpResponse::class)
            ->once()
            ->andReturn($httpResponse);

        $handler->shouldNotReceive('handle');

        $middleware = new AuthMiddleware($container, $config);
        $result = $middleware->process($request, $handler);

        $this->assertSame($jsonResponse, $result);
    }

    /**
     * 请求体缺少 logId 时，应安全处理（传入 null）.
     */
    public function testHandlesMissingLogIdGracefully(): void
    {
        $config = new Config();
        $config->setAccessToken('secret-token');

        $container = m::mock(ContainerInterface::class);
        $request = m::mock(ServerRequestInterface::class);
        $handler = m::mock(RequestHandlerInterface::class);

        $request->shouldReceive('getHeaderLine')
            ->with('xxl-job-access-token')
            ->once()
            ->andReturn('secret-token');

        $request->shouldReceive('getParsedBody')
            ->once()
            ->andReturn([]); // 无 logId 键

        $handler->shouldReceive('handle')->with($request)->once()->andReturn(
            m::mock(ResponseInterface::class)
        );

        $middleware = new AuthMiddleware($container, $config);
        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
