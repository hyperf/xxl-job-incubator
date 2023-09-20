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
namespace Hyperf\XxlJob;

use Hyperf\Engine\Channel;

class ChannelFactory
{
    protected array $channels = [];

    public function get(int $jobId): ?Channel
    {
        if ($this->has($jobId)) {
            return $this->channels[$jobId];
        }

        return $this->channels[$jobId] = new Channel(1);
    }

    public function pop(int $jobId, float $timeout = -1)
    {
        $channel = $this->get($jobId);

        $result = $channel->pop($timeout);
        // Removed channel from factory.
        $this->remove($jobId);
        return $result;
    }

    public function push(int $jobId): void
    {
        $channel = $this->get($jobId);

        if ($channel instanceof Channel) {
            $channel->push($jobId);
        } else {
            $this->remove($jobId);
        }
    }

    public function has(int $jobId): bool
    {
        return array_key_exists($jobId, $this->channels);
    }

    public function remove(int $jobId): void
    {
        unset($this->channels[$jobId]);
    }
}
