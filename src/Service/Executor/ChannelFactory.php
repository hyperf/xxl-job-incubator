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

namespace Hyperf\XxlJob\Service\Executor;

use Hyperf\Engine\Channel;

class ChannelFactory
{
    protected array $channels = [];

    public function get(int $logId): ?Channel
    {
        if ($this->has($logId)) {
            return $this->channels[$logId];
        }

        return $this->channels[$logId] = new Channel(1);
    }

    public function pop(int $logId, float $timeout = -1)
    {
        $channel = $this->get($logId);

        $result = $channel->pop($timeout);
        // Removed channel from factory.
        $this->remove($logId);
        return $result;
    }

    public function push(int $logId): void
    {
        if (! $this->has($logId)) {
            return;
        }

        $channel = $this->get($logId);

        if ($channel instanceof Channel) {
            $channel->push($logId);
        } else {
            $this->remove($logId);
        }
    }

    public function has(int $logId): bool
    {
        return array_key_exists($logId, $this->channels);
    }

    public function remove(int $logId): void
    {
        $this->channels[$logId]?->close();
        unset($this->channels[$logId]);
    }
}
