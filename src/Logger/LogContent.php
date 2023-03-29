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

class LogContent
{
    public function __construct(private string $content, private int $endLine, private bool $isEnd)
    {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getEndLine(): int
    {
        return $this->endLine;
    }

    public function setEndLine(int $endLine): void
    {
        $this->endLine = $endLine;
    }

    public function isEnd(): bool
    {
        return $this->isEnd;
    }

    public function setIsEnd(bool $isEnd): void
    {
        $this->isEnd = $isEnd;
    }
}
