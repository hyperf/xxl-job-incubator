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
namespace Hyperf\XxlJob\Dispatcher;

use Hyperf\HttpServer\Router\RouteCollector;
use Hyperf\XxlJob\Middleware\JobMiddleware;

class XxlJobRoute
{
    public function add(RouteCollector $route, $prefixUrl)
    {
        $route->addGroup('/' . $prefixUrl, function ($route) {
            //心跳
            $route->post('beat', [JobController::class, 'beat']);
            //触发任务执行
            $route->post('run', [JobController::class, 'run']);
            //忙碌检测
            $route->post('idleBeat', [JobController::class, 'idleBeat']);
            //终止任务
            $route->post('kill', [JobController::class, 'kill']);
            //日志
            $route->post('log', [JobController::class, 'log']);
        }, ['middleware' => [JobMiddleware::class]]);
    }
}
