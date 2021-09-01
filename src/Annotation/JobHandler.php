<?php

declare(strict_types=1);

namespace Hyperf\XxlJob\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class JobHandler extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $value = '';

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }
}
