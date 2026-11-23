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

use Hyperf\Codec\Json;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Service\JobAdminService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * 调度中心任务管理命令：xxl-job:job {add|update|remove|start|stop|trigger}
 */
#[Command]
class JobAdminCommand extends HyperfCommand
{
    public const COMMAND_NAME = 'xxl-job:job';

    public function __construct(protected JobAdminService $jobAdminService)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Manage XXL-JOB jobs through the admin OpenAPI (xxl-job >= 3.5.0)');
        $this->addArgument('action', InputArgument::REQUIRED, 'Action: add|update|remove|start|stop|trigger');
        $this->addOption('json', null, InputOption::VALUE_REQUIRED, 'JSON payload string for add/update');
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to a JSON payload file for add/update');
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Job id for update/remove/start/stop/trigger');
        $this->addOption('executor-param', null, InputOption::VALUE_REQUIRED, 'executorParam for trigger');
        $this->addOption('address-list', null, InputOption::VALUE_REQUIRED, 'Comma separated executor addresses for trigger');
    }

    public function handle(): int
    {
        try {
            match ((string) $this->input?->getArgument('action')) {
                'add' => $this->add(),
                'update' => $this->update(),
                'remove' => $this->remove(),
                'start' => $this->start(),
                'stop' => $this->stop(),
                'trigger' => $this->trigger(),
                default => throw new XxlJobException('Unknown action, expected add|update|remove|start|stop|trigger.'),
            };
            return self::SUCCESS;
        } catch (XxlJobException $exception) {
            $this->output?->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            return self::FAILURE;
        }
    }

    protected function add(): void
    {
        $jobId = $this->jobAdminService->addJob($this->resolvePayload());
        $this->output?->writeln(sprintf('<info>Job created, id: %d</info>', $jobId));
    }

    protected function update(): void
    {
        $id = $this->resolveId();
        $payload = $this->resolvePayload();
        // --id 覆盖 payload 中的 id，避免两处不一致
        $payload['id'] = $id;
        $this->jobAdminService->updateJob($payload);
        $this->output?->writeln(sprintf('<info>Job %d updated.</info>', $id));
    }

    protected function remove(): void
    {
        $id = $this->resolveId();
        $this->jobAdminService->removeJob($id);
        $this->output?->writeln(sprintf('<info>Job %d removed.</info>', $id));
    }

    protected function start(): void
    {
        $id = $this->resolveId();
        $this->jobAdminService->startJob($id);
        $this->output?->writeln(sprintf('<info>Job %d started.</info>', $id));
    }

    protected function stop(): void
    {
        $id = $this->resolveId();
        $this->jobAdminService->stopJob($id);
        $this->output?->writeln(sprintf('<info>Job %d stopped.</info>', $id));
    }

    protected function trigger(): void
    {
        $id = $this->resolveId();
        $this->jobAdminService->triggerJob(
            $id,
            (string) ($this->input?->getOption('executor-param') ?? ''),
            (string) ($this->input?->getOption('address-list') ?? '')
        );
        $this->output?->writeln(sprintf('<info>Job %d triggered.</info>', $id));
    }

    /**
     * @throws XxlJobException
     */
    protected function resolvePayload(): array
    {
        $json = (string) ($this->input?->getOption('json') ?? '');
        $file = (string) ($this->input?->getOption('file') ?? '');
        if (($json === '') === ($file === '')) {
            throw new XxlJobException('Please provide exactly one of --json or --file.');
        }
        if ($file !== '') {
            if (! is_file($file) || ! is_readable($file)) {
                throw new XxlJobException(sprintf('The JSON payload file %s is not readable.', $file));
            }
            $json = (string) file_get_contents($file);
        }

        try {
            $payload = Json::decode($json);
        } catch (Throwable $exception) {
            throw new XxlJobException('Invalid JSON payload: ' . $exception->getMessage(), 0, $exception);
        }
        // 空对象解析后是 []，array_is_list([]) 为 true，此处一并拒绝；add/update 必须提供字段
        if (! is_array($payload) || array_is_list($payload)) {
            throw new XxlJobException('The JSON payload must be an object.');
        }
        return $payload;
    }

    /**
     * @throws XxlJobException
     */
    protected function resolveId(): int
    {
        $id = $this->input?->getOption('id');
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new XxlJobException('The --id option must be a positive integer.');
        }
        $id = (int) $id;
        if ($id <= 0) {
            throw new XxlJobException('The --id option must be a positive integer.');
        }
        return $id;
    }
}
