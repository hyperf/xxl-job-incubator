<?php
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

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return int
     */
    public function getEndLine(): int
    {
        return $this->endLine;
    }

    /**
     * @param int $endLine
     */
    public function setEndLine(int $endLine): void
    {
        $this->endLine = $endLine;
    }

    /**
     * @return bool
     */
    public function isEnd(): bool
    {
        return $this->isEnd;
    }

    /**
     * @param bool $isEnd
     */
    public function setIsEnd(bool $isEnd): void
    {
        $this->isEnd = $isEnd;
    }

}