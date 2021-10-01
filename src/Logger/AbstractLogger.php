<?php

namespace Hyperf\XxlJob\Logger;


use Hyperf\Contract\ConfigInterface;
use Hyperf\Framework\Logger\StdoutLogger;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractLogger extends StdoutLogger
{

    protected ConfigInterface $config;

    protected OutputInterface $output;

    protected array $tags = [
        'component',
    ];

}