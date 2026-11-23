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

namespace HyperfTest\XxlJob\Requests;

use Hyperf\XxlJob\Requests\LogRequest;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Requests\LogRequest
 */
class LogRequestTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $req = LogRequest::create([
            'logDateTim' => 20240101120000,
            'logId' => 999,
            'fromLineNum' => 1,
        ]);

        $this->assertSame(20240101120000, $req->getLogDateTim());
        $this->assertSame(999, $req->getLogId());
        $this->assertSame(1, $req->getFromLineNum());
    }

    public function testMissingAndNullFieldsUseDefaults(): void
    {
        $missing = LogRequest::create();
        self::assertSame(0, $missing->getLogDateTim());
        self::assertSame(0, $missing->getLogId());
        self::assertSame(1, $missing->getFromLineNum());

        $null = LogRequest::create([
            'logDateTim' => null,
            'logId' => null,
            'fromLineNum' => null,
        ]);
        self::assertSame(0, $null->getLogDateTim());
        self::assertSame(0, $null->getLogId());
        self::assertSame(1, $null->getFromLineNum());
    }

    public function testCreateFiltersUnknownKeys(): void
    {
        $req = LogRequest::create([
            'logId' => 42,
            'extraKey' => 'should-not-exist',
        ]);

        $this->assertSame(42, $req->getLogId());
    }
}
