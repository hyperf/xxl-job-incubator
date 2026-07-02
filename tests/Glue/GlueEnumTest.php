<?php

declare(strict_types=1);

namespace HyperfTest\XxlJob\Glue;

use Hyperf\XxlJob\Glue\GlueEnum;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Glue\GlueEnum
 */
class GlueEnumTest extends TestCase
{
    public function testIsExists(): void
    {
        $this->assertTrue(GlueEnum::isExists(GlueEnum::BEAN));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_SHELL));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_PYTHON));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_PHP));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_NODEJS));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_POWERSHELL));
        $this->assertTrue(GlueEnum::isExists(GlueEnum::GLUE_GROOVY));

        $this->assertFalse(GlueEnum::isExists('NONEXISTENT'));
    }

    public function testIsScript(): void
    {
        $this->assertFalse(GlueEnum::isScript(GlueEnum::BEAN));
        $this->assertFalse(GlueEnum::isScript(GlueEnum::GLUE_GROOVY));

        $this->assertTrue(GlueEnum::isScript(GlueEnum::GLUE_SHELL));
        $this->assertTrue(GlueEnum::isScript(GlueEnum::GLUE_PYTHON));
        $this->assertTrue(GlueEnum::isScript(GlueEnum::GLUE_PHP));
        $this->assertTrue(GlueEnum::isScript(GlueEnum::GLUE_NODEJS));
        $this->assertTrue(GlueEnum::isScript(GlueEnum::GLUE_POWERSHELL));
    }

    public function testGetDesc(): void
    {
        $this->assertSame('BEAN', GlueEnum::getDesc(GlueEnum::BEAN));
        $this->assertSame('GLUE(Shell)', GlueEnum::getDesc(GlueEnum::GLUE_SHELL));
        $this->assertSame('GLUE(Python)', GlueEnum::getDesc(GlueEnum::GLUE_PYTHON));
    }

    public function testGetCmd(): void
    {
        $this->assertSame('bash', GlueEnum::getCmd(GlueEnum::GLUE_SHELL));
        $this->assertSame('python', GlueEnum::getCmd(GlueEnum::GLUE_PYTHON));
        $this->assertSame('php', GlueEnum::getCmd(GlueEnum::GLUE_PHP));
        $this->assertSame('node', GlueEnum::getCmd(GlueEnum::GLUE_NODEJS));
        $this->assertSame('powershell', GlueEnum::getCmd(GlueEnum::GLUE_POWERSHELL));
    }

    public function testGetSuffix(): void
    {
        $this->assertSame('.sh', GlueEnum::getSuffix(GlueEnum::GLUE_SHELL));
        $this->assertSame('.py', GlueEnum::getSuffix(GlueEnum::GLUE_PYTHON));
        $this->assertSame('.php', GlueEnum::getSuffix(GlueEnum::GLUE_PHP));
        $this->assertSame('.js', GlueEnum::getSuffix(GlueEnum::GLUE_NODEJS));
        $this->assertSame('.ps1', GlueEnum::getSuffix(GlueEnum::GLUE_POWERSHELL));
    }

    public function testBeanHasNoCmdOrSuffix(): void
    {
        $this->assertSame('', GlueEnum::getCmd(GlueEnum::BEAN));
        $this->assertSame('', GlueEnum::getSuffix(GlueEnum::BEAN));
    }
}
