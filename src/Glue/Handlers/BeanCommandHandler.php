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

use Hyperf\XxlJob\Requests\RunRequest;

class BeanCommandHandler extends BeanHandler implements GlueHandlerInterface
{
    public function handle(RunRequest $request)
    {
        $executorHandler = $request->getExecutorHandler();
        $jobDefinition = $this->jobHandlerManager->getJobHandlers($executorHandler);
        $this->jobRun->execute($request, $this->executeCallable($jobDefinition));
    }
}
