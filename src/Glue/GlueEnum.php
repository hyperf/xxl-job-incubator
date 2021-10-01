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
namespace Hyperf\XxlJob\Glue;

class GlueEnum
{
    public const BEAN = 'BEAN';

    public const GLUE_GROOVY = 'GLUE_GROOVY';

    public const GLUE_SHELL = 'GLUE_SHELL';

    public const GLUE_PYTHON = 'GLUE_PYTHON';

    public const GLUE_PHP = 'GLUE_PHP';

    public const GLUE_NODEJS = 'GLUE_NODEJS';

    public const GLUE_POWERSHELL = 'GLUE_POWERSHELL';

    public static array $enums = [
        self::BEAN => ['BEAN', false, null, null],
        self::GLUE_GROOVY => ['GLUE(Java)', false, null, null],
        self::GLUE_SHELL => ['GLUE(Shell)', true, 'bash', '.sh'],
        self::GLUE_PYTHON => ['GLUE(Python)', true, 'python', '.py'],
        self::GLUE_PHP => ['GLUE(PHP)', true, 'php', '.php'],
        self::GLUE_NODEJS => ['GLUE(Nodejs)', true, 'node', '.js'],
        self::GLUE_POWERSHELL => ['GLUE(PowerShell)', true, 'powershell', '.ps1'],
    ];

    public static function isExists(string $enum): bool
    {
        return isset(static::$enums[$enum]);
    }

    public static function isScript(string $enum): bool
    {
        return (bool) static::$enums[$enum][1];
    }

    public static function getDesc(string $enum): string
    {
        return (string) static::$enums[$enum][0];
    }

    public static function getSuffix(string $enum): string
    {
        return (string) static::$enums[$enum][3];
    }

    public static function getCmd(string $enum): string
    {
        return (string) static::$enums[$enum][2];
    }
}
