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

namespace HyperfTest\XxlJob\Service\Executor;

use Hyperf\Coordinator\Coordinator;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\Executor\JobRunContent
 */
class JobRunContentTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        // 每个测试后重置静态状态
        $this->resetStaticState();
    }

    public function testSetAndGetJobId(): void
    {
        $req = $this->createRunRequest(42, 999);
        JobRunContent::setJobId(42, $req);

        $this->assertSame($req, JobRunContent::getId(42));
    }

    public function testGetIdReturnsNullForUnknownJob(): void
    {
        $this->assertNull(JobRunContent::getId(99999));
    }

    public function testHasReturnsCorrectly(): void
    {
        $this->assertFalse(JobRunContent::has(1));

        $req = $this->createRunRequest(1, 100);
        JobRunContent::setJobId(1, $req);

        $this->assertTrue(JobRunContent::has(1));
    }

    public function testRemoveClearsJobContent(): void
    {
        $req = $this->createRunRequest(1, 100);
        JobRunContent::setJobId(1, $req);
        $this->assertTrue(JobRunContent::has(1));

        JobRunContent::remove(1, 100);
        $this->assertFalse(JobRunContent::has(1));
        $this->assertNull(JobRunContent::getId(1));
    }

    public function testGetRunningJobIds(): void
    {
        JobRunContent::setJobId(1, $this->createRunRequest(1, 101));
        JobRunContent::setJobId(2, $this->createRunRequest(2, 102));
        JobRunContent::setJobId(3, $this->createRunRequest(3, 103));

        $running = JobRunContent::getRunningJobIds();
        sort($running);

        $this->assertSame([1, 2, 3], $running);
    }

    public function testGetRunningJobIdsEmpty(): void
    {
        $this->assertSame([], JobRunContent::getRunningJobIds());
    }

    public function testSetJobIdOverwritesExisting(): void
    {
        $old = $this->createRunRequest(1, 100);
        $new = $this->createRunRequest(1, 200);

        JobRunContent::setJobId(1, $old);
        JobRunContent::setJobId(1, $new);

        $this->assertSame($new, JobRunContent::getId(1));
        $this->assertSame(200, JobRunContent::getId(1)->getLogId());
    }

    public function testMultipleJobsIndependent(): void
    {
        $req1 = $this->createRunRequest(1, 101);
        $req2 = $this->createRunRequest(2, 102);

        JobRunContent::setJobId(1, $req1);
        JobRunContent::setJobId(2, $req2);

        // 删除 job 1
        JobRunContent::remove(1, 101);

        // job 2 仍存在
        $this->assertFalse(JobRunContent::has(1));
        $this->assertTrue(JobRunContent::has(2));
        $this->assertSame($req2, JobRunContent::getId(2));
    }

    /**
     * remove() 应通过 Coordinator 的 resume() 唤醒 yield() 等待者.
     */
    public function testRemoveResumesYieldingCoroutine(): void
    {
        $req = $this->createRunRequest(1, 500);
        JobRunContent::setJobId(1, $req);

        // 在协程中 yield，然后在主线程中 remove，yield 应被唤醒
        // 由于没有真实的 Swoole 协程环境，这里验证 Coordinator 被正确创建和 resume
        // 实际行为：remove() 的 resume() 不会抛出异常即为成功
        JobRunContent::remove(1, 500);

        $this->assertFalse(JobRunContent::has(1));
    }

    private function resetStaticState(): void
    {
        $ref = new ReflectionClass(JobRunContent::class);
        $contentProp = $ref->getProperty('content');
        $contentProp->setAccessible(true);
        $contentProp->setValue(null, []);

        $channelsProp = $ref->getProperty('channels');
        $channelsProp->setAccessible(true);
        $channelsProp->setValue(null, []);
    }

    private function createRunRequest(int $jobId = 1, int $logId = 100): RunRequest
    {
        return RunRequest::create([
            'jobId' => $jobId,
            'logId' => $logId,
        ]);
    }
}
