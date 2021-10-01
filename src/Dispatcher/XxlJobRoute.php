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
use Hyperf\XxlJob\Middleware\AuthMiddleware;

class XxlJobRoute
{
    public function add(RouteCollector $route, $prefixUrl)
    {
        $route->addGroup('/' . $prefixUrl, function ($route) {
            $route->post('beat', [JobController::class, 'beat']);
            $route->post('run', [JobController::class, 'run']);
            $route->post('idleBeat', [JobController::class, 'idleBeat']);
            $route->post('kill', [JobController::class, 'kill']);
            $route->post('log', [JobController::class, 'log']);
        }, ['middleware' => [AuthMiddleware::class]]);
    }
}
