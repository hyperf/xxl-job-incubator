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
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;

abstract class AbstractJobExecutor implements JobExecutorInterface
{
    private ?JobRunInfoStore $runInfoStore = null;

    public function __construct(
        protected ContainerInterface $container,
        protected StdoutLoggerInterface $stdoutLogger,
        protected ApiRequest $apiRequest,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected Config $config,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isRun(int $jobId): bool
    {
        return JobRunContent::has($jobId);
    }

    /*
     * Bean final execute method
     */
    public function execute(RunRequest $request, callable $callback): void
    {
        try {
            JobContext::setJobLogId($request->getLogId());
            $request->setExtension('cid', Coroutine::id());
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
            $this->jobExecutorLogger->error($message);
            $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
            throw $throwable;
        } finally {
            JobRunContent::remove($request->getJobId(), $request->getLogId());
            // AfterJobRun
            $this->eventDispatcher->dispatch(new AfterJobRun($request));
        }
    }

    protected function jobRunInfoStore(): JobRunInfoStore
    {
        // Store 无进程内状态，按同一 Config 延迟创建即可让各 worker 使用同一套文件协议
        return $this->runInfoStore ??= new JobRunInfoStore($this->config);
    }
}
