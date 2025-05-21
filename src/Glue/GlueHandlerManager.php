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

namespace Hyperf\XxlJob\Glue;

use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\Handlers\BeanHandler;
use Hyperf\XxlJob\Glue\Handlers\GlueHandlerInterface;
use Hyperf\XxlJob\Glue\Handlers\ScriptHandler;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Psr\Container\ContainerInterface;

class GlueHandlerManager
{
    protected array $handlers = [
        GlueEnum::BEAN => BeanHandler::class,
        GlueEnum::GLUE_SHELL => ScriptHandler::class,
        GlueEnum::GLUE_PYTHON => ScriptHandler::class,
        GlueEnum::GLUE_PHP => ScriptHandler::class,
        GlueEnum::GLUE_NODEJS => ScriptHandler::class,
        GlueEnum::GLUE_POWERSHELL => ScriptHandler::class,
    ];

    public function __construct(protected ContainerInterface $container)
    {
    }

    public function handle(string $glueType, RunRequest $request)
    {
        JobRunContent::setJobId($request->getJobId(), $request);
        if (! GlueEnum::isExists($glueType) || ! isset($this->handlers[$glueType])) {
            JobRunContent::remove($request->getJobId());
            throw new XxlJobException('Glue type is invalid or does not support yet');
        }
        $instance = $this->container->get($this->handlers[$glueType]);
        if (! $instance instanceof GlueHandlerInterface) {
            JobRunContent::remove($request->getJobId());
            throw new XxlJobException(sprintf('The glue handler %s is invalid handler, should be implement %s', $this->handlers[$glueType], GlueHandlerInterface::class));
        }
        $instance->handle($request);
    }
}
