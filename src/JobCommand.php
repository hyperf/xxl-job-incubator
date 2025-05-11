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

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\XxlJob\Glue\Handlers\BeanCommandHandler;
use Hyperf\XxlJob\Requests\RunRequest;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class JobCommand extends HyperfCommand
{
    public const COMMAND_NAME = 'execute:xxl-job';

    public function __construct(
        protected BeanCommandHandler $handler,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Execute xxl-job');
        $this->addOption('runRequest', 'r', InputOption::VALUE_REQUIRED, 'xxl-job runRequest json');
    }

    public function handle()
    {
        $data = $this->input->getOptions();
        if (! $data['runRequest']) {
            var_dump('runRequest not know');
            return;
        }

        $runArr = json_decode($data['runRequest'], true);
        $runRequest = RunRequest::create($runArr);
        $this->handler->handle($runRequest);
    }
}
