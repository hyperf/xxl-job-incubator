<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob\Requests;

use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use Hyperf\XxlJob\Requests\RunRequest;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Requests\BaseRequest
 * @covers \Hyperf\XxlJob\Requests\RunRequest
 */
class RunRequestTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $data = [
            'jobId' => 1,
            'executorHandler' => 'demoJobHandler',
            'executorParams' => '--foo=bar',
            'executorBlockStrategy' => ExecutorBlockStrategyEnum::SERIAL_EXECUTION,
            'executorTimeout' => 60,
            'logId' => 12345,
            'logDateTime' => 20240101120000,
            'glueType' => 'BEAN',
            'glueSource' => 'echo hello',
            'glueUpdatetime' => 20240101000000,
            'broadcastIndex' => 0,
            'broadcastTotal' => 1,
        ];

        $req = RunRequest::create($data);

        $this->assertSame(1, $req->getJobId());
        $this->assertSame('demoJobHandler', $req->getExecutorHandler());
        $this->assertSame('--foo=bar', $req->getExecutorParams());
        $this->assertSame(ExecutorBlockStrategyEnum::SERIAL_EXECUTION, $req->getExecutorBlockStrategy());
        $this->assertSame(60, $req->getExecutorTimeout());
        $this->assertSame(12345, $req->getLogId());
        $this->assertSame(20240101120000, $req->getLogDateTime());
        $this->assertSame('BEAN', $req->getGlueType());
        $this->assertSame('echo hello', $req->getGlueSource());
        $this->assertSame(20240101000000, $req->getGlueUpdatetime());
        $this->assertSame(0, $req->getBroadcastIndex());
        $this->assertSame(1, $req->getBroadcastTotal());
    }

    public function testCreateFiltersUnknownKeys(): void
    {
        $req = RunRequest::create([
            'jobId' => 42,
            'unknownField' => 'should-be-ignored',
        ]);

        $this->assertSame(42, $req->getJobId());
        // 验证未知字段不会导致异常
    }

    public function testIsCoverEarly(): void
    {
        $req = RunRequest::create([
            'executorBlockStrategy' => ExecutorBlockStrategyEnum::COVER_EARLY,
        ]);
        $this->assertTrue($req->isCoverEarly());
        $this->assertFalse($req->isCoverLater());
    }

    public function testIsCoverLater(): void
    {
        $req = RunRequest::create([
            'executorBlockStrategy' => ExecutorBlockStrategyEnum::DISCARD_LATER,
        ]);
        $this->assertTrue($req->isCoverLater());
        $this->assertFalse($req->isCoverEarly());
    }

    public function testSerialExecutionNeither(): void
    {
        $req = RunRequest::create([
            'executorBlockStrategy' => ExecutorBlockStrategyEnum::SERIAL_EXECUTION,
        ]);
        $this->assertFalse($req->isCoverEarly());
        $this->assertFalse($req->isCoverLater());
    }

    public function testExtension(): void
    {
        $req = RunRequest::create(['jobId' => 1]);

        $this->assertNull($req->getExtension('nonexistent'));

        $req->setExtension('cid', 123);
        $this->assertSame(123, $req->getExtension('cid'));

        $req->setExtension('process', 'pid-999');
        $this->assertSame('pid-999', $req->getExtension('process'));
    }

    public function testJsonSerialize(): void
    {
        $req = RunRequest::create(['jobId' => 1, 'logId' => 100]);
        $json = $req->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('jobId', $json);
        $this->assertArrayHasKey('logId', $json);
    }
}
