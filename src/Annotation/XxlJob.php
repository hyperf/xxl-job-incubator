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
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class XxlJob extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $value = '';

    /**
     * @var string
     */
    public $init = '';

    /**
     * @var string
     */
    public $destroy = '';

    public function __construct(string $value = '', string $init = '', string $destroy = '')
    {
        $this->value = $value;
        $this->init = $init;
        $this->destroy = $destroy;
    }
}
