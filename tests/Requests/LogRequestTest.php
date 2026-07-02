<?php

declare(strict_types=1);

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
            'logDateTim'  => 20240101120000,
            'logId'       => 999,
            'fromLineNum' => 1,
        ]);

        $this->assertSame(20240101120000, $req->getLogDateTim());
        $this->assertSame(999, $req->getLogId());
        $this->assertSame(1, $req->getFromLineNum());
    }

    public function testCreateFiltersUnknownKeys(): void
    {
        $req = LogRequest::create([
            'logId'    => 42,
            'extraKey' => 'should-not-exist',
        ]);

        $this->assertSame(42, $req->getLogId());
    }

    public function testEmptyCreate(): void
    {
        $req = LogRequest::create([]);
        $this->assertSame(0, $req->getLogId());
        $this->assertSame(0, $req->getLogDateTim());
        $this->assertSame(0, $req->getFromLineNum());
    }
}
