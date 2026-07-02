<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\ApiRequest
 */
class ApiRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testRegistryBuildsCorrectPayload(): void
    {
        $config = new Config();
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('/xxl-job-admin');
        $config->setAccessToken('test-token');

        // Use a partial mock to verify request() is called with correct params
        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('request')
            ->with('POST', '/api/registry', m::on(function ($options) {
                $body = $options[RequestOptions::JSON];
                return $body['registryGroup'] === 'EXECUTOR'
                    && $body['registryKey'] === 'my-app'
                    && $body['registryValue'] === 'http://localhost:9501/my-executor';
            }))
            ->once()
            ->andReturn(m::mock(ResponseInterface::class));

        $apiRequest->registry('my-app', 'http://localhost:9501/my-executor');
        $this->addToAssertionCount(1);
    }

    public function testRegistryRemoveBuildsCorrectPayload(): void
    {
        $config = new Config();
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('/xxl-job-admin');

        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('request')
            ->with('POST', '/api/registryRemove', m::on(function ($options) {
                $body = $options[RequestOptions::JSON];
                return $body['registryGroup'] === 'EXECUTOR';
            }))
            ->once()
            ->andReturn(m::mock(ResponseInterface::class));

        $apiRequest->registryRemove('my-app', 'http://localhost/my');
        $this->addToAssertionCount(1);
    }

    public function testCallbackBuildsCorrectPayload(): void
    {
        $config = new Config();
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('/xxl-job-admin');

        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('request')
            ->with('POST', '/api/callback', m::on(function ($options) {
                $body = $options[RequestOptions::JSON];
                $item = $body[0];
                return $item['logId'] === 999
                    && $item['logDateTim'] === 20240101120000
                    && $item['handleCode'] === 500
                    && $item['handleMsg'] === 'Something went wrong'
                    && $item['executeResult']['code'] === 500;
            }))
            ->once()
            ->andReturn(m::mock(ResponseInterface::class));

        $apiRequest->callback(999, 20240101120000, 500, 'Something went wrong');
        $this->addToAssertionCount(1);
    }

    public function testCallbackDefaultHandleCode(): void
    {
        $config = new Config();
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('/xxl-job-admin');

        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('request')
            ->with('POST', '/api/callback', m::on(function ($options) {
                $body = $options[RequestOptions::JSON];
                return $body[0]['handleCode'] === 200;
            }))
            ->once()
            ->andReturn(m::mock(ResponseInterface::class));

        $apiRequest->callback(1, 20240101000000);
        $this->addToAssertionCount(1);
    }

    public function testMultipleCallbackEmptyData(): void
    {
        $config = new Config();
        $apiRequest = new ApiRequest($config);

        $result = $apiRequest->multipleCallback([]);
        $this->assertNull($result);
    }

    public function testMultipleCallbackBuildsCorrectPayload(): void
    {
        $config = new Config();
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('/xxl-job-admin');

        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('request')
            ->with('POST', '/api/callback', m::on(function ($options) {
                $body = $options[RequestOptions::JSON];
                return count($body) === 2
                    && $body[0]['logId'] === 1
                    && $body[1]['logId'] === 2;
            }))
            ->once()
            ->andReturn(m::mock(ResponseInterface::class));

        $apiRequest->multipleCallback([
            ['logId' => 1, 'logDateTim' => 100],
            ['logId' => 2, 'logDateTim' => 200],
        ], 500, 'killed');
        $this->addToAssertionCount(1);
    }

    public function testRequestInjectsTokenHeader(): void
    {
        $config = new Config();
        $config->setAccessToken('secret');
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('');
        $config->setGuzzleConfig(['timeout' => 10]);

        $response = m::mock(ResponseInterface::class);

        $guzzleClient = m::mock(Client::class);
        $guzzleClient->shouldReceive('request')
            ->with('POST', '/api/test', m::on(function ($options) {
                return isset($options[RequestOptions::HEADERS]['XXL-JOB-ACCESS-TOKEN'])
                    && $options[RequestOptions::HEADERS]['XXL-JOB-ACCESS-TOKEN'] === 'secret';
            }))
            ->once()
            ->andReturn($response);

        // Partially mock to replace createClient
        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('createClient')->once()->andReturn($guzzleClient);

        $result = $apiRequest->request('POST', '/api/test');
        $this->assertSame($response, $result);
    }

    public function testRequestSkipsTokenWhenEmpty(): void
    {
        $config = new Config();
        $config->setAccessToken(''); // 空 token
        $config->setBaseUri('http://admin.test:8080');
        $config->setServerUrlPath('');
        $config->setGuzzleConfig(['timeout' => 10]);

        $response = m::mock(ResponseInterface::class);

        $guzzleClient = m::mock(Client::class);
        $guzzleClient->shouldReceive('request')
            ->with('POST', '/api/test', m::on(function ($options) {
                return ! isset($options[RequestOptions::HEADERS]['XXL-JOB-ACCESS-TOKEN']);
            }))
            ->once()
            ->andReturn($response);

        $apiRequest = m::mock(ApiRequest::class, [$config])->makePartial();
        $apiRequest->shouldReceive('createClient')->once()->andReturn($guzzleClient);

        $result = $apiRequest->request('POST', '/api/test');
        $this->assertSame($response, $result);
    }
}
