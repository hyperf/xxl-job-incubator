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

namespace HyperfTest\XxlJob\Logger;

use Hyperf\XxlJob\Logger\LogContent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Logger\LogContent
 */
class LogContentTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $lc = new LogContent('log line 1', 10, false);

        $this->assertSame('log line 1', $lc->getContent());
        $this->assertSame(10, $lc->getEndLine());
        $this->assertFalse($lc->isEnd());
    }

    public function testConstructorWithEndTrue(): void
    {
        $lc = new LogContent('', 5, true);
        $this->assertTrue($lc->isEnd());
    }

    public function testSetters(): void
    {
        $lc = new LogContent('', 0, false);

        $lc->setContent('updated');
        $this->assertSame('updated', $lc->getContent());

        $lc->setEndLine(42);
        $this->assertSame(42, $lc->getEndLine());

        $lc->setIsEnd(true);
        $this->assertTrue($lc->isEnd());
    }

    public function testEndLineCanBeZero(): void
    {
        $lc = new LogContent('', 0, false);
        $this->assertSame(0, $lc->getEndLine());
    }
}
