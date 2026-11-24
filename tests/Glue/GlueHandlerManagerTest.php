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

namespace HyperfTest\XxlJob\Glue;

use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Glue\GlueHandlerManager
 */
class GlueHandlerManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testSupportsRegisteredGlueTypes(): void
    {
        $manager = new GlueHandlerManager(m::mock(ContainerInterface::class));

        $this->assertTrue($manager->supports('BEAN'));
        $this->assertTrue($manager->supports('GLUE_SHELL'));
        $this->assertTrue($manager->supports('GLUE_PYTHON'));
    }

    public function testDoesNotSupportGlueTypeWithoutHandler(): void
    {
        $manager = new GlueHandlerManager(m::mock(ContainerInterface::class));

        // GLUE_GROOVY 只在 GlueEnum 中声明，没有对应的 handler 实现，必须在 HTTP 侧就被拒绝
        $this->assertFalse($manager->supports('GLUE_GROOVY'));
    }

    public function testDoesNotSupportUnknownGlueType(): void
    {
        $manager = new GlueHandlerManager(m::mock(ContainerInterface::class));

        $this->assertFalse($manager->supports('GLUE_UNKNOWN'));
    }
}
