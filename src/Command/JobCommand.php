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

namespace Hyperf\XxlJob\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Container;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\Handlers\BeanCommandHandler;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Logger\JobExecutorStdoutLogger;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class JobCommand extends HyperfCommand
{
    public const COMMAND_NAME = 'execute:xxl-job';

    public function __construct(
        protected BeanCommandHandler $handler,
        protected JobExecutorProcess $jobExecutorProcess,
        protected Container $container,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Execute a scheduled job by matching jobId and logId, or invoke a handler directly for debugging');
        $this->addOption('jobId', 'j', InputOption::VALUE_OPTIONAL, 'Scheduled job ID (requires --logId); use --handler for direct debugging');
        $this->addOption('logId', 'l', InputOption::VALUE_OPTIONAL, 'Required with --jobId; must match the scheduled run info');

        // Direct invocation from command line without XXL-JOB admin
        $this->addOption('handler', null, InputOption::VALUE_OPTIONAL, 'The executor handler name for direct CLI invocation');
        $this->addOption('params', null, InputOption::VALUE_OPTIONAL, 'The executor params passed to the handler');
    }

    public function handle()
    {
        $data = $this->input->getOptions();
        if (empty($data['jobId']) && empty($data['handler'])) {
            throw new XxlJobException('JobId or handler cannot be empty');
        }
        if (! empty($data['jobId'])) {
            $jobId = intval($data['jobId']);
            $logId = intval($data['logId'] ?? 0);
            $infoArr = $this->jobExecutorProcess->getJobFileInfo($jobId);
            if (empty($infoArr) || ($infoArr['logId'] ?? null) !== $logId) {
                // 旧子进程绝不能读取并执行同 jobId 的新一代请求
                throw new XxlJobException(sprintf('Run info for JobId:%s LogId:%s is missing or belongs to another logId.', $jobId, $logId));
            }
            $this->handler->handle($infoArr['runRequest']);
        } elseif (! empty($data['handler'])) {
            $this->container->define(JobExecutorLoggerInterface::class, JobExecutorStdoutLogger::class);
            $runRequestArr = [
                'executorHandler' => $data['handler'],
                'jobId' => 0,
                'logId' => intval($data['logId'] ?? 0),
                'logDateTime' => intval(microtime(true) * 1000),
                'executorParams' => $data['params'] ?? '',
                'executorBlockStrategy' => 'SERIAL_EXECUTION',
            ];
            $runRequest = RunRequest::create($runRequestArr);
            $this->handler->handle($runRequest);
        } else {
            throw new XxlJobException('logId is required when using jobId, or use --handler for direct call');
        }
    }
}
