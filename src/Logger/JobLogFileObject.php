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
namespace Hyperf\XxlJob\Logger;

use SplFileObject;

class JobLogFileObject extends SplFileObject
{
    public function getContent(int $from, int $limit): array
    {
        $this->seek($from);
        $content = '';
        while (! $this->eof()) {
            $current = $this->current();
            if (empty($current) || ($limit !== -1 && ($this->key() - $from) >= $limit)) {
                break;
            }
            $content .= $current;
            $this->next();
        }
        $endLine = $this->key();
        return [$content, $endLine, $this->eof()];
    }
}
