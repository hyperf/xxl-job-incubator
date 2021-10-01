<?php

namespace Hyperf\XxlJob\Logger;


use Hyperf\Contract\ConfigInterface;
use Hyperf\Framework\Logger\StdoutLogger;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractLogger extends StdoutLogger
{

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string[]
     */
    protected $tags = [
        'component',
    ];

}