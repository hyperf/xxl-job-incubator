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

namespace HyperfTest\XxlJob\Dispatcher;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteParser\Std;
use Hyperf\HttpServer\Router\RouteCollector;
use Hyperf\XxlJob\Dispatcher\JobController;
use Hyperf\XxlJob\Dispatcher\XxlJobRoute;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Dispatcher\XxlJobRoute
 */
class XxlJobRouteTest extends TestCase
{
    public function testRegistersTriggerRoute(): void
    {
        $collector = new RouteCollector(new Std(), new GroupCountBased());
        (new XxlJobRoute())->add($collector, 'php-xxl-job/');

        $staticRoutes = $collector->getData()[0];

        // 3.5.0 起调度中心使用 /trigger，不再注册旧的 /run
        $this->assertSame([JobController::class, 'trigger'], $staticRoutes['POST']['/php-xxl-job/trigger']->callback);
        $this->assertArrayNotHasKey('/php-xxl-job/run', $staticRoutes['POST']);

        foreach (['beat', 'idleBeat', 'kill', 'log'] as $action) {
            $this->assertArrayHasKey('/php-xxl-job/' . $action, $staticRoutes['POST']);
        }
    }
}
