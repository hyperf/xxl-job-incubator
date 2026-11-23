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

namespace Hyperf\XxlJob\Requests;

use JetBrains\PhpStorm\Pure;
use ReflectionProperty;

class BaseRequest
{
    #[Pure]
    public static function create(array $data = []): static
    {
        $obj = new static();
        foreach ($data as $k => $v) {
            if (! property_exists($obj, $k)) {
                continue;
            }
            // admin 可能显式返回 null：有声明默认值的字段沿用默认值，必填字段仍保留类型校验
            if ($v === null && (new ReflectionProperty($obj, $k))->hasDefaultValue()) {
                continue;
            }
            $obj->{$k} = $v;
        }
        return $obj;
    }
}
