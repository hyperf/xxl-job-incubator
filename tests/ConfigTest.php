<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Config;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Config
 */
class ConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Config();

        $this->assertFalse($config->isEnable());
        $this->assertSame('http://127.0.0.1:8080', $config->getBaseUri());
        $this->assertSame('', $config->getAccessToken());
        $this->assertSame('', $config->getServerUrlPath());
        $this->assertSame('', $config->getAppName());
        $this->assertSame('', $config->getClientUrl());
        $this->assertSame(30, $config->getHeartbeat());
        $this->assertSame('', $config->getExecutorServerHost());
        $this->assertSame(9501, $config->getExecutorServerPort());
        $this->assertSame('', $config->getExecutorServerPrefixUrl());
        $this->assertSame(-1, $config->getLogRetentionDays());
        $this->assertSame([], $config->getStartCommand());
        $this->assertSame('', $config->getLogFileDir());
        $this->assertSame('', $config->getExecutionMode());
        $this->assertSame(0, $config->getMaxProcessLifetime());
    }

    public function testDefaultGuzzleConfig(): void
    {
        $config = new Config();
        $guzzle = $config->getGuzzleConfig();

        $this->assertSame('UTF-8', $guzzle['headers']['charset']);
        $this->assertSame(10, $guzzle['timeout']);
    }

    public function testEnable(): void
    {
        $config = new Config();
        $config->setEnable(true);
        $this->assertTrue($config->isEnable());

        $config->setEnable(false);
        $this->assertFalse($config->isEnable());
    }

    public function testBaseUri(): void
    {
        $config = new Config();
        $config->setBaseUri('https://example.com:8443');
        $this->assertSame('https://example.com:8443', $config->getBaseUri());
    }

    public function testAccessToken(): void
    {
        $config = new Config();
        $config->setAccessToken('my-token');
        $this->assertSame('my-token', $config->getAccessToken());
    }

    public function testGuzzleConfig(): void
    {
        $config = new Config();
        $config->setGuzzleConfig(['timeout' => 30, 'headers' => ['X-Foo' => 'Bar']]);
        $this->assertSame(['timeout' => 30, 'headers' => ['X-Foo' => 'Bar']], $config->getGuzzleConfig());
    }

    public function testAppName(): void
    {
        $config = new Config();
        $config->setAppName('my-app');
        $this->assertSame('my-app', $config->getAppName());
    }

    public function testClientUrl(): void
    {
        $config = new Config();
        $config->setClientUrl('http://192.168.1.1:9501/php-xxl-job');
        $this->assertSame('http://192.168.1.1:9501/php-xxl-job', $config->getClientUrl());
    }

    public function testHeartbeat(): void
    {
        $config = new Config();
        $config->setHeartbeat(60);
        $this->assertSame(60, $config->getHeartbeat());
    }

    public function testExecutorServerHost(): void
    {
        $config = new Config();
        $config->setExecutorServerHost('10.0.0.1');
        $this->assertSame('10.0.0.1', $config->getExecutorServerHost());
    }

    public function testExecutorServerPort(): void
    {
        $config = new Config();
        $config->setExecutorServerPort(8080);
        $this->assertSame(8080, $config->getExecutorServerPort());
    }

    public function testExecutorServerPrefixUrl(): void
    {
        $config = new Config();
        $config->setExecutorServerPrefixUrl('my-executor');
        $this->assertSame('my-executor', $config->getExecutorServerPrefixUrl());
    }

    public function testLogRetentionDays(): void
    {
        $config = new Config();
        $config->setLogRetentionDays(7);
        $this->assertSame(7, $config->getLogRetentionDays());
    }

    public function testStartCommand(): void
    {
        $config = new Config();
        $config->setStartCommand(['php', 'bin/hyperf.php', 'start']);
        $this->assertSame(['php', 'bin/hyperf.php', 'start'], $config->getStartCommand());
    }

    public function testLogFileDir(): void
    {
        $config = new Config();
        $config->setLogFileDir('/var/log/xxl-job');
        $this->assertSame('/var/log/xxl-job', $config->getLogFileDir());
    }

    public function testExecutionMode(): void
    {
        $config = new Config();
        $config->setExecutionMode('process');
        $this->assertSame('process', $config->getExecutionMode());

        $config->setExecutionMode('coroutine');
        $this->assertSame('coroutine', $config->getExecutionMode());
    }

    public function testMaxProcessLifetime(): void
    {
        $config = new Config();
        $config->setMaxProcessLifetime(86400);
        $this->assertSame(86400, $config->getMaxProcessLifetime());

        // 0 means never restart
        $config->setMaxProcessLifetime(0);
        $this->assertSame(0, $config->getMaxProcessLifetime());
    }

    public function testFluentSetters(): void
    {
        $config = new Config();
        $result = $config->setEnable(true);

        // 大部分 setter 返回 Config 实例（链式调用）
        $this->assertSame($config, $result);
    }
}
