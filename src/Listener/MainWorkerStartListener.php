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
namespace Hyperf\XxlJob\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Hyperf\XxlJob\Application;
use Throwable;

class MainWorkerStartListener implements ListenerInterface
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function __construct(Application $app, StdoutLoggerInterface $logger)
    {
        $this->app = $app;
        $this->logger = $logger;
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    public function process(object $event)
    {
        $config = $this->app->getConfig();
        if (!$config->isEnable()) {
            return;
        }
        $this->registerHeartbeat($config->getAppName(), $config->getClientUrl(), $config->getHeartbeat());
    }

    protected function registerHeartbeat(string $appName, string $url, $heartbeat = 30): void
    {
        $isFirstRegister = true;
        Coroutine::create(function () use ($appName, $url, $heartbeat, $isFirstRegister) {
            retry(INF, function () use ($appName, $url, $heartbeat, $isFirstRegister) {
                while (true) {
                    if (!$isFirstRegister && CoordinatorManager::until(Constants::WORKER_EXIT)->yield($heartbeat)) {
                        break;
                    }
                    $isFirstRegister = false;
                    try {
                        $response = $this->app->service->registry($appName, $url);
                        $result = Json::decode((string) $response->getBody());
                        if ($result['code'] == 200) {
                            $this->logger->debug(sprintf('xxlJob registry app name:%s heartbeat successfully', $appName));
                        } else {
                            $this->logger->error(sprintf('xxlJob registry app name:%s fail, %s', $appName, $result['msg']));
                        }
                    } catch (Throwable $throwable) {
                        $this->logger->error(sprintf('xxlJob registry failed. %s',$throwable->getMessage()));
                    }
                }
            });
        });
    }

}
