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

class Config
{
    private bool $enable = false;

    private string $baseUri = 'http://127.0.0.1:8080';

    private string $accessToken = '';

    private string $serverUrlPath = '';

    private array $guzzleConfig = [
        'headers' => [
            'charset' => 'UTF-8',
        ],
        'timeout' => 10,
    ];

    private string $appName = '';

    private string $clientUrl = '';

    private int $heartbeat = 30;

    private string $executorServerPrefixUrl = '';

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): Config
    {
        $this->enable = $enable;
        return $this;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function setBaseUri(string $baseUri): Config
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): Config
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getServerUrlPath(): string
    {
        return $this->serverUrlPath;
    }

    public function setServerUrlPath(string $serverUrlPath): Config
    {
        $this->serverUrlPath = $serverUrlPath;
        return $this;
    }

    public function getGuzzleConfig(): array
    {
        return $this->guzzleConfig;
    }

    public function setGuzzleConfig(array $guzzleConfig): Config
    {
        $this->guzzleConfig = $guzzleConfig;
        return $this;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function setAppName(string $appName): Config
    {
        $this->appName = $appName;
        return $this;
    }

    public function getClientUrl(): string
    {
        return $this->clientUrl;
    }

    public function setClientUrl(string $clientUrl): Config
    {
        $this->clientUrl = $clientUrl;
        return $this;
    }

    public function getHeartbeat(): int
    {
        return $this->heartbeat;
    }

    public function setHeartbeat(int $heartbeat): Config
    {
        $this->heartbeat = $heartbeat;
        return $this;
    }

    public function getExecutorServerPrefixUrl(): string
    {
        return $this->executorServerPrefixUrl;
    }

    public function setExecutorServerPrefixUrl(string $executorServerPrefixUrl): Config
    {
        $this->executorServerPrefixUrl = $executorServerPrefixUrl;
        return $this;
    }
}
