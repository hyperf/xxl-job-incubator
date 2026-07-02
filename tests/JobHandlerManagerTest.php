<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Exception\RegisterJobHandlerException;
use Hyperf\XxlJob\JobHandlerDefinition;
use Hyperf\XxlJob\JobHandlerManager;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\JobHandlerManager
 */
class JobHandlerManagerTest extends TestCase
{
    public function testRegisterAndGetHandler(): void
    {
        $manager = new JobHandlerManager();
        $definition = new JobHandlerDefinition('App\Job\FooJob', 'execute', 'init', 'destroy', 'process');

        $manager->registerJobHandler('fooHandler', $definition);

        $this->assertSame($definition, $manager->getJobHandlers('fooHandler'));
    }

    public function testGetUnknownHandlerReturnsNull(): void
    {
        $manager = new JobHandlerManager();
        $this->assertNull($manager->getJobHandlers('nonexistent'));
    }

    public function testDuplicateRegistrationThrowsException(): void
    {
        $manager = new JobHandlerManager();
        $definition1 = new JobHandlerDefinition('App\Job\FooJob', 'execute', '', '', 'process');
        $definition2 = new JobHandlerDefinition('App\Job\BarJob', 'execute', '', '', 'process');

        $manager->registerJobHandler('myHandler', $definition1);

        $this->expectException(RegisterJobHandlerException::class);
        $this->expectExceptionMessage('xxl-job jobHandler myHandler naming conflicts.');

        $manager->registerJobHandler('myHandler', $definition2);
    }

    public function testMultipleHandlers(): void
    {
        $manager = new JobHandlerManager();
        $def1 = new JobHandlerDefinition('App\Job\FooJob', 'execute', '', '', 'coroutine');
        $def2 = new JobHandlerDefinition('App\Job\BarJob', 'run', '', '', 'process');

        $manager->registerJobHandler('foo', $def1);
        $manager->registerJobHandler('bar', $def2);

        $this->assertSame($def1, $manager->getJobHandlers('foo'));
        $this->assertSame($def2, $manager->getJobHandlers('bar'));
        // 不应影响另一个 handler
        $this->assertNull($manager->getJobHandlers('baz'));
    }
}
