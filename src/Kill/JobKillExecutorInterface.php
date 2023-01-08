<?php

namespace Hyperf\XxlJob\Kill;

interface JobKillExecutorInterface
{
    public function kill(int $jobId): void;
}