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
namespace Hyperf\XxlJob;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class ApiRequest
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param mixed $method
     * @param mixed $uri
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method, $uri, array $options = []): ResponseInterface
    {
        $token = $this->config->getAccessToken();
        $uri = $this->config->getServerUrlPath() . $uri;
        $token && $options[RequestOptions::HEADERS]['XXL-JOB-ACCESS-TOKEN'] = $token;
        return $this->createClient()->request($method, $uri, $options);
    }

    public function createClient(): Client
    {
        $config = array_merge($this->config->getGuzzleConfig(), [
            'base_uri' => $this->config->getBaseUri(),
        ]);
        return new Client($config);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function registry(string $registryKey, string $registryValue): ResponseInterface
    {
        $body = [
            'registryGroup' => 'EXECUTOR',
            'registryKey' => $registryKey,
            'registryValue' => $registryValue,
        ];
        return $this->request('POST', '/api/registry', [
            RequestOptions::JSON => $body,
        ]);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function registryRemove(string $registryKey, string $registryValue): ResponseInterface
    {
        $body = [
            'registryGroup' => 'EXECUTOR',
            'registryKey' => $registryKey,
            'registryValue' => $registryValue,
        ];
        return $this->request('POST', '/api/registryRemove', [
            RequestOptions::JSON => $body,
        ]);
    }

    /**
     * @param null|mixed $handleMsg
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function callback(int $logId, int $logDateTim, int $handleCode = 200, $handleMsg = null): ResponseInterface
    {
        $body = [[
            'logId' => $logId,
            'logDateTim' => $logDateTim,
            'handleCode' => $handleCode,
            'handleMsg' => $handleMsg,
            'executeResult' => [
                'code' => $handleCode,
                'msg' => $handleMsg,
            ],
        ]];
        return $this->request('POST', '/api/callback', [
            RequestOptions::JSON => $body,
        ]);
    }
}
