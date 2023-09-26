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
namespace Hyperf\XxlJob\Run;

use Hyperf\Coroutine\Coroutine;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\ChannelFactory;
use Hyperf\XxlJob\Event\AfterJobRun;
use Hyperf\XxlJob\Event\BeforeJobRun;
use Hyperf\XxlJob\JobContext;
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
        protected JobContent $jobContent,
        protected ChannelFactory $channelFactory,
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
            $request->setId(Coroutine::id());
            $this->jobContent->setJobId($request->getJobId(), $request->getJobId(), $request);
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
            $this->jobContent->remove($request->getJobId(), $request->getJobId());
            $this->channelFactory->push($request->getJobId());
            // AfterJobRun
            $this->eventDispatcher->dispatch(new AfterJobRun($request));
        }
    }

    public function command(RunRequest $request): void
    {
        $str = json_encode($request);
        $file = BASE_PATH . '/bin/hyperf.php';
        $cmd = sprintf("php %s xxl-job:command -r '%s' 2>&1", $file, $str);
        Coroutine::create(function () use ($cmd, $request) {
            shell_exec($cmd);
            $this->channelFactory->push($request->getJobId());
        });
    }
}
