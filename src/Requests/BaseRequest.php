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

class BaseRequest
{
    #[Pure]
    public static function create(array $data = []): static
    {
        $obj = new static();
        foreach ($data as $k => $v) {
            if (property_exists($obj, $k)) {
                $obj->{$k} = $v;
            }
        }
        return $obj;
    }
}
