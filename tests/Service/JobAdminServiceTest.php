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

namespace HyperfTest\XxlJob\Service;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\RequestOptions;
use Hyperf\Codec\Json;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Service\JobAdminService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\JobAdminService
 */
class JobAdminServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testAddJobReturnsNewJobId(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->with('POST', '/api/addJob', m::on(static function (array $options): bool {
                $body = $options[RequestOptions::JSON];
                return $body['jobGroup'] === 1
                    && $body['name'] === 'demo'
                    // admin 会对 null 调 trim()，客户端必须补空串
                    && $body['executorHandler'] === ''
                    && $body['scheduleConf'] === '';
            }))
            ->andReturn($this->response(['code' => 200, 'msg' => null, 'data' => '123']));

        $jobId = (new JobAdminService($apiRequest))->addJob([
            'jobGroup' => '1',
            'name' => 'demo',
            'author' => 'admin',
            'scheduleType' => 'NONE',
            'executorRouteStrategy' => 'FIRST',
            'executorBlockStrategy' => 'SERIAL_EXECUTION',
            'glueType' => 'BEAN',
        ]);

        $this->assertSame(123, $jobId);
    }

    public function testAddJobRejectsInvalidJobGroupWithoutRequest(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldNotReceive('request');

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('jobGroup');

        (new JobAdminService($apiRequest))->addJob(['jobGroup' => 0]);
    }

    public function testAddJobRejectsMissingResponseData(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->andReturn($this->response(['code' => 200, 'msg' => null, 'data' => null]));

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('valid job id');

        (new JobAdminService($apiRequest))->addJob(['jobGroup' => 1]);
    }

    public function testUpdateJobRemovesJobGroupAndDefaultsStrings(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->with('POST', '/api/updateJob', m::on(static function (array $options): bool {
                $body = $options[RequestOptions::JSON];
                return $body['id'] === 5
                    && ! array_key_exists('jobGroup', $body)
                    && $body['executorHandler'] === ''
                    && $body['scheduleConf'] === '';
            }))
            ->andReturn($this->response(['code' => 200, 'msg' => null]));

        (new JobAdminService($apiRequest))->updateJob([
            'id' => '5',
            'jobGroup' => 99,
            'name' => 'demo',
            'author' => 'admin',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testUpdateJobRejectsInvalidIdWithoutRequest(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldNotReceive('request');

        $this->expectException(XxlJobException::class);

        (new JobAdminService($apiRequest))->updateJob(['id' => 0]);
    }

    /**
     * @dataProvider simpleActionProvider
     */
    public function testSimpleActionsBuildIdBody(string $method, string $uri): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->with('POST', $uri, m::on(static fn (array $options): bool => $options[RequestOptions::JSON] === ['id' => 7]))
            ->andReturn($this->response(['code' => 200, 'msg' => null]));

        $service = new JobAdminService($apiRequest);
        $service->{$method}(7);

        $this->addToAssertionCount(1);
    }

    public static function simpleActionProvider(): array
    {
        return [
            ['removeJob', '/api/removeJob'],
            ['startJob', '/api/startJob'],
            ['stopJob', '/api/stopJob'],
        ];
    }

    /**
     * @dataProvider simpleActionProvider
     */
    public function testSimpleActionsRejectInvalidIdWithoutRequest(string $method): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldNotReceive('request');

        $this->expectException(XxlJobException::class);

        (new JobAdminService($apiRequest))->{$method}(0);
    }

    public function testTriggerJobOmitsEmptyOptionalFields(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->with('POST', '/api/triggerJob', m::on(static fn (array $options): bool => $options[RequestOptions::JSON] === ['id' => 7]))
            ->andReturn($this->response(['code' => 200, 'msg' => null]));

        (new JobAdminService($apiRequest))->triggerJob(7);

        $this->addToAssertionCount(1);
    }

    public function testTriggerJobPassesOptionalFields(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->with('POST', '/api/triggerJob', m::on(static function (array $options): bool {
                return $options[RequestOptions::JSON] === [
                    'id' => 7,
                    'executorParam' => '{"k":"v"}',
                    'addressList' => 'http://127.0.0.1:9999/',
                ];
            }))
            ->andReturn($this->response(['code' => 200, 'msg' => null]));

        (new JobAdminService($apiRequest))->triggerJob(7, '{"k":"v"}', 'http://127.0.0.1:9999/');

        $this->addToAssertionCount(1);
    }

    public function testAdminFailureThrowsWithMessage(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->andReturn($this->response(['code' => 500, 'msg' => 'invalid request, accessToken or appname invalid.']));

        try {
            (new JobAdminService($apiRequest))->removeJob(1);
            $this->fail('expected XxlJobException');
        } catch (XxlJobException $exception) {
            $this->assertStringContainsString('/api/removeJob', $exception->getMessage());
            $this->assertStringContainsString('invalid request, accessToken or appname invalid.', $exception->getMessage());
        }
    }

    public function testRateLimitFailureMentionsRateLimit(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->andReturn($this->response(['code' => 502, 'msg' => 'Too many requests, please try again later.']));

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('rate limited');

        (new JobAdminService($apiRequest))->startJob(1);
    }

    public function testEmptyResponseThrows(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')->once()->andReturn(new Psr7Response(200, [], ''));

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('empty response');

        (new JobAdminService($apiRequest))->stopJob(1);
    }

    public function testInvalidJsonResponseThrows(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')->once()->andReturn(new Psr7Response(200, [], '<html>login</html>'));

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('invalid JSON');

        (new JobAdminService($apiRequest))->stopJob(1);
    }

    public function testScalarJsonResponseThrows(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')->once()->andReturn(new Psr7Response(200, [], '1'));

        $this->expectException(XxlJobException::class);
        $this->expectExceptionMessage('unexpected payload');

        (new JobAdminService($apiRequest))->stopJob(1);
    }

    public function testGuzzleExceptionIsWrapped(): void
    {
        $apiRequest = m::mock(ApiRequest::class);
        $apiRequest->shouldReceive('request')
            ->once()
            ->andThrow(new ConnectException('connection refused', new Psr7Request('POST', '/api/stopJob')));

        try {
            (new JobAdminService($apiRequest))->stopJob(1);
            $this->fail('expected XxlJobException');
        } catch (XxlJobException $exception) {
            $this->assertStringContainsString('connection refused', $exception->getMessage());
            $this->assertInstanceOf(ConnectException::class, $exception->getPrevious());
        }
    }

    protected function response(array $body): Psr7Response
    {
        return new Psr7Response(200, [], Json::encode($body));
    }
}
