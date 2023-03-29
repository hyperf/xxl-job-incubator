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

use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Utils\Coroutine;
use Hyperf\XxlJob\Event\AfterJobRun;
use Hyperf\XxlJob\Event\BeforeJobRun;
use Hyperf\XxlJob\Kill\JobKillContent;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class JobRun
{
    public function __construct(
        protected ContainerInterface $container,
        protected EventDispatcherInterface $eventDispatcher,
        protected JobExecutorLoggerInterface $jobExecutorLogger,
        protected ApiRequest $apiRequest,
        protected JobKillContent $jobKillContent,
    ) {
    }

    public function execute(RunRequest $request, callable $callback): int
    {
        return Coroutine::create(function () use ($request, $callback) {
            try {
                $this->jobKillContent->setJobId($request->getJobId(), Coroutine::id());
                // BeforeJobRun
                $this->eventDispatcher->dispatch(new BeforeJobRun($request));
                JobContext::setJobLogId($request->getLogId());
                JobContext::setRunRequest($request);
                $this->jobExecutorLogger->info(sprintf('Beginning, with params: %s', $request->getExecutorParams() ?: '[NULL]'));

                $callback($request);

                $this->jobExecutorLogger->info('Finished');
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime());
            } catch (Throwable $throwable) {
                $message = $throwable->getMessage();
                if ($this->container->has(FormatterInterface::class)) {
                    $formatter = $this->container->get(FormatterInterface::class);
                    $message = $formatter->format($throwable);
                    $message = str_replace(PHP_EOL, '<br>', $message);
                }
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
                $this->jobExecutorLogger->error($message);
                throw $throwable;
            } finally {
                $this->jobKillContent->unsetJobId($request->getJobId());
                // AfterJobRun
                $this->eventDispatcher->dispatch(new AfterJobRun($request));
            }
        });
    }
}
