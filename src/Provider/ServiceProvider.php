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
namespace Hyperf\XxlJob\Provider;

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class ServiceProvider extends AbstractProvider
{
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
