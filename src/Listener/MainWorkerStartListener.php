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
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\XxlJobException;
use Throwable;

class MainWorkerStartListener implements ListenerInterface
{
    protected Config $xxlConfig;

    protected StdoutLoggerInterface $logger;

    protected ApiRequest $apiRequest;

    public function __construct(Config $xxlConfig, StdoutLoggerInterface $logger, ApiRequest $apiRequest)
    {
        $this->xxlConfig = $xxlConfig;
        $this->logger = $logger;
        $this->apiRequest = $apiRequest;
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
        if (! $this->xxlConfig->isEnable()) {
            return;
        }
        $this->registerHeartbeat($this->xxlConfig->getAppName(), $this->xxlConfig->getClientUrl(), $this->xxlConfig->getHeartbeat());
    }

    protected function registerHeartbeat(string $appName, string $url, int $heartbeat): void
    {
        $isFirstRegister = true;
        Coroutine::create(function () use ($appName, $url, $heartbeat, $isFirstRegister) {
            retry(INF, function () use ($appName, $url, $heartbeat, $isFirstRegister) {
                while (true) {
                    if (! $isFirstRegister && CoordinatorManager::until(Constants::WORKER_EXIT)->yield($heartbeat)) {
                        break;
                    }
                    try {
                        $response = $this->apiRequest->registry($appName, $url);
                        $result = Json::decode((string) $response->getBody());
                        if ($result['code'] == 200) {
                            if ($isFirstRegister) {
                                $this->logger->info(sprintf('Register XXL-JOB app name [%s] is successful', $appName));
                            } else {
                                $this->logger->debug('XXL-JOB Executor heartbeat is successful');
                            }
                            $isFirstRegister = false;
                        } else {
                            throw new XxlJobException($result['msg']);
                        }
                    } catch (Throwable $throwable) {
                        $this->logger->error(sprintf('Failed to register XXL-JOB executor with message: %s', $throwable->getMessage()));
                        throw $throwable;
                    }
                }
            }, $heartbeat * 1000);
        });
    }
}
