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
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\Handlers\BeanCommandHandler;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRun;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class JobCommand extends HyperfCommand
{
    public const COMMAND_NAME = 'execute:xxl-job';

    public function __construct(
        protected BeanCommandHandler $handler,
        protected JobExecutorProcess $jobExecutorProcess,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Execute xxl-job');
        $this->addOption('jobId', 'j', InputOption::VALUE_REQUIRED, 'the job id executed by xxl job');
        $this->addOption('logId', 'l', InputOption::VALUE_OPTIONAL, 'the log id executed by xxl job');
    }

    public function handle()
    {
        $data = $this->input->getOptions();
        if (! $data['jobId']) {
            throw new XxlJobException('JobId cannot be empty');
        }
        $jobId = intval($data['jobId']);
        $infoArr = $this->jobExecutorProcess->getJobFileInfo($jobId);
        $this->handler->handle($infoArr['runRequest']);
    }
}
