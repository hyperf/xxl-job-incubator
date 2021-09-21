<?php

namespace Hyperf\XxlJob;


class JobDefinition
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

    public function setClass(string $class): JobDefinition
    {
        $this->class = $class;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): JobDefinition
    {
        $this->method = $method;
        return $this;
    }

    public function getInit(): string
    {
        return $this->init;
    }

    public function setInit(string $init): JobDefinition
    {
        $this->init = $init;
        return $this;
    }

    public function getDestroy(): string
    {
        return $this->destroy;
    }

    public function setDestroy(string $destroy): JobDefinition
    {
        $this->destroy = $destroy;
        return $this;
    }


}