<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\JobPipeMessage;
use Hyperf\XxlJob\Requests\RunRequest;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\JobPipeMessage
 */
class JobPipeMessageTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $msg = new JobPipeMessage();

        $this->assertNull($msg->runRequest);
        $this->assertSame(0, $msg->killJobId);
        $this->assertSame(-1, $msg->fromWorkerId);
    }

    public function testWithRunRequest(): void
    {
        $req = RunRequest::create(['jobId' => 42, 'logId' => 999]);
        $msg = new JobPipeMessage(runRequest: $req);

        $this->assertSame($req, $msg->runRequest);
        $this->assertSame(0, $msg->killJobId);
    }

    public function testWithKillJobId(): void
    {
        $msg = new JobPipeMessage(killJobId: 123);

        $this->assertNull($msg->runRequest);
        $this->assertSame(123, $msg->killJobId);
    }

    public function testWithFromWorkerId(): void
    {
        $msg = new JobPipeMessage(fromWorkerId: 5);

        $this->assertSame(5, $msg->fromWorkerId);
    }
}
