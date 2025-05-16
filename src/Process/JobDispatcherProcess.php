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

namespace Hyperf\XxlJob\Process;

use Hyperf\Engine\Channel;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Exception\SocketAcceptException;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Socket;
use Throwable;

class JobDispatcherProcess extends AbstractProcess
{
    public const JOB_DISPATCHER_NAME = 'xxj-job-dispatcher';

    public string $name = self::JOB_DISPATCHER_NAME;

    public int $nums = 1;

    protected Config $xxlConfig;

    protected JobSerialExecutionService $jobSerialExecutionService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->xxlConfig = $container->get(Config::class);
        $this->jobSerialExecutionService = $container->get(JobSerialExecutionService::class);
    }

    public function isEnable($server): bool
    {
        return $this->xxlConfig->isEnable();
    }

    public function handle(): void
    {
    }

    protected function listen(Channel $quit)
    {
        while ($quit->pop(0.001) !== true) {
            try {
                /** @var Socket $sock */
                $sock = $this->process->exportSocket();
                $recv = $sock->recv($this->recvLength, $this->recvTimeout);
                if ($recv === '') {
                    throw new SocketAcceptException('Socket is closed', $sock->errCode);
                }

                if ($recv === false && $sock->errCode !== SOCKET_ETIMEDOUT) {
                    throw new SocketAcceptException('Socket is closed', $sock->errCode);
                }

                if ($this->event && $recv !== false && $data = unserialize($recv)) {
                    if ($data instanceof JobPipeMessage) {
                        $this->dispatcher($data);
                    }
                }
            } catch (Throwable $exception) {
                $this->logThrowable($exception);
                if ($exception instanceof SocketAcceptException) {
                    break;
                }
            }
        }
        $quit->close();
    }

    protected function dispatcher(JobPipeMessage $jobPipeMessage): void
    {
        $this->jobSerialExecutionService->handle($jobPipeMessage);
    }
}
