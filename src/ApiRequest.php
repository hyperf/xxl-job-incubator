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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class ApiRequest
{
    protected Config $config;

    private ?JobCallbackGuard $callbackGuard = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @throws GuzzleException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
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
     * @throws GuzzleException
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
     * @throws GuzzleException
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
     * @throws GuzzleException
     */
    public function callback(int $logId, int $logDateTim, int $handleCode = 200, $handleMsg = null): ?ResponseInterface
    {
        if ($logId <= 0) {
            return null;
        }
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
        return $this->callbackGuard()->execute($logId, function () use ($body): ResponseInterface {
            return $this->request('POST', '/api/callback', [
                RequestOptions::JSON => $body,
            ]);
        });
    }

    public function multipleCallback(array $data, int $handleCode = 200, $handleMsg = null): ?ResponseInterface
    {
        if (empty($data)) {
            return null;
        }
        $items = [];
        foreach ($data as $item) {
            $items[$item['logId']] = $item;
        }

        $response = null;
        // 分片限制同时持有的文件锁数量，避免大批量失败回调耗尽进程文件句柄
        foreach (array_chunk($items, 50, true) as $chunk) {
            $response = $this->callbackGuard()->executeMany(array_keys($chunk), function (array $pendingIds) use ($chunk, $handleCode, $handleMsg): ResponseInterface {
                $body = [];
                foreach ($pendingIds as $logId) {
                    $item = $chunk[$logId];
                    $body[] = [
                        'logId' => $logId,
                        'logDateTim' => $item['logDateTim'] ?? 0,
                        'handleCode' => $item['handleCode'] ?? $handleCode,
                        'handleMsg' => $item['handleMsg'] ?? $handleMsg,
                        'executeResult' => [
                            'code' => $item['handleCode'] ?? $handleCode,
                            'msg' => $item['handleMsg'] ?? $handleMsg,
                        ],
                    ];
                }
                return $this->request('POST', '/api/callback', [
                    RequestOptions::JSON => $body,
                ]);
            });
        }
        return $response;
    }

    protected function callbackGuard(): JobCallbackGuard
    {
        return $this->callbackGuard ??= new JobCallbackGuard($this->config);
    }
}
