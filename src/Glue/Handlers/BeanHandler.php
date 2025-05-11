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

use Closure;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Requests\RunRequest;

class BeanHandler extends AbstractGlueHandler implements GlueHandlerInterface
{
    public function handle(RunRequest $request)
    {
        $executorHandler = $request->getExecutorHandler();
        $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);
        $this->executionMode($request, $jobDefinition);
    }

    protected function executionMode($request, $jobDefinition): void
    {
        switch ($jobDefinition->getExecutionMode()) {
            case XxlJob::COROUTINE:
                $this->jobExecutorCoroutine->run($request, $this->executeCallable($jobDefinition));
                break;
            case XxlJob::PROCESS:
                $this->jobExecutorProcess->run($request, null);
                break;
            default:
        }
    }

    protected function executeCallable($jobDefinition): Closure
    {
        return function (RunRequest $request) use ($jobDefinition) {
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
        };
    }
}
