<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob\Enum;

use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum
 */
class ExecutorBlockStrategyEnumTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('SERIAL_EXECUTION', ExecutorBlockStrategyEnum::SERIAL_EXECUTION);
        $this->assertSame('DISCARD_LATER', ExecutorBlockStrategyEnum::DISCARD_LATER);
        $this->assertSame('COVER_EARLY', ExecutorBlockStrategyEnum::COVER_EARLY);
    }
}
