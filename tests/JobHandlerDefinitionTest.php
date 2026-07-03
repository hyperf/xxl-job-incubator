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

namespace HyperfTest\XxlJob;

use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\JobHandlerDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\JobHandlerDefinition
 */
class JobHandlerDefinitionTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $def = new JobHandlerDefinition(
            'App\Job\MyJob',
            'execute',
            'init',
            'destroy',
            'process'
        );

        $this->assertSame('App\Job\MyJob', $def->getClass());
        $this->assertSame('execute', $def->getMethod());
        $this->assertSame('init', $def->getInit());
        $this->assertSame('destroy', $def->getDestroy());
        $this->assertSame('process', $def->getExecutionMode());
    }

    public function testDefaultEmptyInitAndDestroy(): void
    {
        $def = new JobHandlerDefinition('App\Job\Foo', 'handle', '', '', 'coroutine');

        $this->assertSame('', $def->getInit());
        $this->assertSame('', $def->getDestroy());
    }

    public function testDefaultExecutionMode(): void
    {
        $def = new JobHandlerDefinition('App\Job\Bar', 'execute', '', '', XxlJob::PROCESS);
        $this->assertSame('process', $def->getExecutionMode());

        $defCoroutine = new JobHandlerDefinition('App\Job\Baz', 'execute', '', '', XxlJob::COROUTINE);
        $this->assertSame('coroutine', $defCoroutine->getExecutionMode());
    }

    public function testFluentSetters(): void
    {
        $def = new JobHandlerDefinition('', '', '', '', '');

        $result = $def->setClass('Foo')
            ->setMethod('bar')
            ->setInit('init')
            ->setDestroy('cleanup')
            ->setExecutionMode('process');

        $this->assertSame($def, $result);
        $this->assertSame('Foo', $def->getClass());
        $this->assertSame('bar', $def->getMethod());
        $this->assertSame('init', $def->getInit());
        $this->assertSame('cleanup', $def->getDestroy());
        $this->assertSame('process', $def->getExecutionMode());
    }
}
