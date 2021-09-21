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

use Exception;
use Hyperf\XxlJob\Provider\ServiceProvider;
use JetBrains\PhpStorm\ArrayShape;

/**
 * @property ServiceProvider $service
 */
class Application
{
    protected $alias = [
        'service' => ServiceProvider::class,
    ];

    /**
     * @var array
     */
    protected $providers = [];

    /**
     * @var array
     */
    protected $jobHandlerDefinitions = [];

    /**
     * @var Config
     */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param mixed $name
     * @throws Exception
     */
    public function __get($name)
    {
        if (! isset($name) || ! isset($this->alias[$name])) {
            throw new Exception("{$name} is invalid.");
        }

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $class = $this->alias[$name];
        return $this->providers[$name] = new $class($this->config);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getJobHandlerDefinitions(string $jobName): ?JobDefinition
    {
        return $this->jobHandlerDefinitions[$jobName] ?? null;
    }

    public function registerJobHandler(string $jobName, JobDefinition $definition): void
    {
        $this->jobHandlerDefinitions[$jobName] = $definition;
    }
}
