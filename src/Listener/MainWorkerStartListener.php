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

use Hyperf\Codec\Json;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Logger\JobExecutorFileLogger;
use Throwable;

use function Hyperf\Support\retry;

class MainWorkerStartListener implements ListenerInterface
{
    public function __construct(
        protected Config $xxlConfig,
        protected StdoutLoggerInterface $logger,
        protected ApiRequest $apiRequest,
        protected JobExecutorFileLogger $jobExecutorFileLogger,
    ) {}

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $this->xxlConfig->isEnable()) {
            return;
        }
        $this->deleteExpiredFiles($this->xxlConfig->getLogRetentionDays());
        $this->registerHeartbeat($this->xxlConfig->getAppName(), $this->xxlConfig->getClientUrl(), $this->xxlConfig->getHeartbeat());
    }

    protected function deleteExpiredFiles(int $logRetentionDays): void
    {
        if ($logRetentionDays < 3) {
            return;
        }
        Coroutine::create(function () use ($logRetentionDays) {
            while (true) {
                if (CoordinatorManager::until(Constants::WORKER_EXIT)->yield(24 * 3600)) {
                    break;
                }
                try {
                    $this->logger->info('XXL-JOB delete expired files, log retention days : '.$logRetentionDays);
                    $logFiles = glob($this->xxlConfig->getLogFileDir() . '*.log');
                    foreach ($logFiles as $file) {
                        if (time() - filectime($file) > $logRetentionDays * 24 * 3600) {
                            is_writable($file) && unlink($file);
                        }
                    }
                } catch (Throwable $throwable) {
                    $this->logger->error($throwable);
                }
            }
        });
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
