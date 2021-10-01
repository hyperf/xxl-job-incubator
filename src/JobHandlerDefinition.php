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

class JobHandlerDefinition
{
    protected string $class = '';

    protected string $method = '';

    protected string $init = '';

    protected string $destroy = '';

    public function __construct(string $class, string $method, string $init, string $destroy)
    {
        $this->setClass($class)->setMethod($method)->setInit($init)->setDestroy($destroy);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): JobHandlerDefinition
    {
        $this->class = $class;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): JobHandlerDefinition
    {
        $this->method = $method;
        return $this;
    }

    public function getInit(): string
    {
        return $this->init;
    }

    public function setInit(string $init): JobHandlerDefinition
    {
        $this->init = $init;
        return $this;
    }

    public function getDestroy(): string
    {
        return $this->destroy;
    }

    public function setDestroy(string $destroy): JobHandlerDefinition
    {
        $this->destroy = $destroy;
        return $this;
    }
}
