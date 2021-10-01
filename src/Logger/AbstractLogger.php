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
