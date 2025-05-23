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

namespace Hyperf\XxlJob\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class XxlJob extends AbstractAnnotation
{
    public const COROUTINE = 'coroutine';

    public const PROCESS = 'process';

    public string $value = '';

    public string $init = '';

    public string $destroy = '';

    public string $executionMode = '';

    public function __construct(string $value = '', string $init = '', string $destroy = '', string $executionMode = '')
    {
        $this->value = $value;
        $this->init = $init;
        $this->destroy = $destroy;
        $this->executionMode = $executionMode;
    }
}
