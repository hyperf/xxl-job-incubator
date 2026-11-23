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

use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Service\Executor\JobRunContent
 */
class JobRunContentGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(JobRunContent::class);
        foreach (['content', 'channels'] as $name) {
            $property = $ref->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }

    public function testYieldReturnsCompleteWhenRegistrationWasAlreadyRemoved(): void
    {
        self::assertTrue(JobRunContent::yield(999, 1));
    }

    public function testStaleLogIdCannotRemoveNewGeneration(): void
    {
        $oldRequest = RunRequest::create(['jobId' => 1, 'logId' => 100]);
        $newRequest = RunRequest::create(['jobId' => 1, 'logId' => 200]);
        JobRunContent::setJobId(1, $oldRequest);
        JobRunContent::setJobId(1, $newRequest);

        // 旧任务收尾只能唤醒自己的 Coordinator，不能删掉同 jobId 的新登记
        JobRunContent::remove(1, 100);
        self::assertSame($newRequest, JobRunContent::getId(1));

        JobRunContent::remove(1, 200);
        self::assertFalse(JobRunContent::has(1));
    }
}
