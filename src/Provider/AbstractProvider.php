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

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Hyperf\XxlJob\Config;

abstract class AbstractProvider
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function request($method, $uri, array $options = [])
    {
        $token = $this->config->getAccessToken();
        $uri = $this->config->getServerUrlPrefix() . $uri;
        $token && $options[RequestOptions::HEADERS]['XXL-JOB-ACCESS-TOKEN'] = $token;
        return $this->client()->request($method, $uri, $options);
    }

    public function client(): Client
    {
        $config = array_merge($this->config->getGuzzleConfig(), [
            'base_uri' => $this->config->getBaseUri(),
        ]);
        return new Client($config);
    }
}
