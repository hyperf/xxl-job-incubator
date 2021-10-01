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
namespace Hyperf\XxlJob\Glue\Handlers;

use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\JobHandlerDefinition;
use Hyperf\XxlJob\Requests\RunRequest;
use Throwable;

class BeanHandler extends AbstractGlueHandler implements GlueHandlerInterface
{
    public function handle(RunRequest $request)
    {
        $executorHandler = $request->getExecutorHandler();
        $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);

        if (empty($jobDefinition) || ! method_exists($jobDefinition->getClass(), $jobDefinition->getMethod())) {
            throw new GlueHandlerExecutionException(sprintf('The definition of executor handler %s is invalid.', $executorHandler));
        }

        JobContext::runJob($jobDefinition, $request, function (JobHandlerDefinition $jobDefinition, RunRequest $request) {
            try {
                $this->jobExecutorLogger->info(sprintf('Beginning, with params: %s', $request->getExecutorParams() ?: '[NULL]'));

                $jobInstance = $this->container->get($jobDefinition->getClass());
                $init = $jobDefinition->getInit();
                $method = $jobDefinition->getMethod();
                $destroy = $jobDefinition->getDestroy();

                if (! empty($init) && method_exists($jobInstance, $init)) {
                    $jobInstance->{$init}($request);
                }

                $jobInstance->{$method}($request);

                if (! empty($destroy) && method_exists($jobInstance, $destroy)) {
                    $jobInstance->{$destroy}($request);
                }
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
            }
        });
    }
}
