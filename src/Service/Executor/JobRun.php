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

namespace Hyperf\XxlJob\Service\Executor;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Event\AfterJobRun;
use Hyperf\XxlJob\Event\BeforeJobRun;
use Hyperf\XxlJob\JobCommand;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class JobRun
{
    public function __construct(
        protected ContainerInterface $container,
        protected EventDispatcherInterface $eventDispatcher,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected ApiRequest $apiRequest,
        protected Config $config,
        protected StdoutLoggerInterface $stdoutLogger,
    ) {
    }

    public function executeCoroutine(RunRequest $request, callable $callback): int
    {
        return Coroutine::create(function () use ($request, $callback) {
            $this->execute($request, $callback);
        });
    }

    public function execute(RunRequest $request, callable $callback): void
    {
        try {
            JobContext::setJobLogId($request->getLogId());
            $request->setId(Coroutine::id());
            // BeforeJobRun
            $this->eventDispatcher->dispatch(new BeforeJobRun($request));
            JobContext::setRunRequest($request);
            $this->jobExecutorLogger->info(sprintf('Beginning, with params: %s', $request->getExecutorParams() ?: '[NULL]'));

            $callback($request);

            $this->jobExecutorLogger->info('Finished');
            $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime());
        } catch (ProcessSignaledException $e) {
            $message = sprintf('XXL-JOB: JobId:%s LogId:%s warning:%s', $request->getJobId(), $request->getLogId(), $e->getMessage());
            $this->stdoutLogger->warning($message);
        } catch (ProcessTimedOutException) {
            $msg = 'scheduling center kill job. [job running, killed]';
            $this->jobExecutorLogger->warning($msg);
            $this->stdoutLogger->warning($msg . ' JobId:' . $request->getJobId());
            $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $msg);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            if ($this->container->has(FormatterInterface::class)) {
                $formatter = $this->container->get(FormatterInterface::class);
                $message = $formatter->format($throwable);
                // $message = str_replace(PHP_EOL, '<br>', $message);
            }
            $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
            $this->jobExecutorLogger->error($message);
            throw $throwable;
        } finally {
            JobRunContent::remove($request->getJobId(), $request->getLogId());
            // AfterJobRun
            $this->eventDispatcher->dispatch(new AfterJobRun($request));
        }
    }

    public function executeCommand(RunRequest $request): void
    {
        $command = $this->config->getStartCommand();
        $command[] = JobCommand::COMMAND_NAME;
        $command[] = '-j';
        $command[] = $request->getJobId();
        $command[] = '-l';
        $command[] = $request->getLogId();
        $this->stdoutLogger->debug('XXL-JOB execute commands:' . implode(' ', $command));
        Coroutine::create(function () use ($command, $request) {
            try {
                JobContext::setJobLogId($request->getLogId());
                $executorTimeout = $request->getExecutorTimeout();
                $process = new Process($command, timeout: $executorTimeout > 0 ? $executorTimeout : null);
                $process->start();
                $filename = $this->putJobFileInfo($process->getPid(), $request);
                $process->wait(
                    // function ($type, $buffer): void {
                    //     $buffer = trim($buffer);
                    //     if ($type === Process::ERR) {
                    //         $this->stdoutLogger->error($buffer);
                    //     } else {
                    //         $this->stdoutLogger->info($buffer);
                    //     }
                    // }
                );
            } catch (ProcessSignaledException $e) {
                $message = sprintf('XXL-JOB: JobId:%s LogId:%s warning:%s', $request->getJobId(), $request->getLogId(), $e->getMessage());
                $this->stdoutLogger->warning($message);
            } catch (ProcessTimedOutException) {
                $msg = 'scheduling center kill job. [job running, killed]';
                $this->jobExecutorLogger->warning($msg);
                $this->stdoutLogger->warning($msg . ' JobId:' . $request->getJobId());
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $msg);
            } finally {
                ! empty($filename) && @unlink($filename);
                JobRunContent::remove($request->getJobId(), $request->getLogId());
            }
        });
    }

    public function putJobFileInfo(int $pid, RunRequest $request): string
    {
        $filename = $this->config->getLogFileDir() . sprintf('jobId_%s.info', $request->getJobId());
        $data['pid'] = $pid;
        $data['createTime'] = time();
        $data['runRequest'] = $request;
        file_put_contents($filename, json_encode($data));
        return $filename;
    }

    public function getJobFileInfo(int $jobId): array
    {
        $data = [];
        $path = $this->config->getLogFileDir() . sprintf('jobId_%s.info', $jobId);
        if (file_exists($path)) {
            $strInfo = file_get_contents($path);
            $infoArr = json_decode($strInfo, true);
            $data['pid'] = $infoArr['pid'];
            $data['createTime'] = $infoArr['createTime'];
            $data['runRequest'] = RunRequest::create($infoArr['runRequest']);
            $data['filePath'] = $path;
            return $data;
        }
        return $data;
    }
}
