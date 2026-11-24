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

namespace HyperfTest\XxlJob\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\Event\PipeMessage;
use Hyperf\XxlJob\Api\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Listener\OnPipeMessageListener;
use Hyperf\XxlJob\Process\JobPipeFileStore;
use Hyperf\XxlJob\Process\JobPipeMessage;
use Hyperf\XxlJob\Service\JobSerialExecutionService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Listener\OnPipeMessageListener
 */
class OnPipeMessageListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testKillFailurePublishesFailureReply(): void
    {
        $config = new Config();
        $config->setAppName('ack-listener-test');
        $config->setLogFileDir('/tmp/ack-listener-' . uniqid('', true));
        $store = new JobPipeFileStore($config);
        $replyId = str_repeat('c', 32);
        try {
            $serialService = m::mock(JobSerialExecutionService::class);
            $serialService->shouldReceive('handle')->once()->with(null, 1)->andThrow(new XxlJobException('kill failed'));
            $logger = m::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('error')->once()->with('kill failed');
            $listener = new OnPipeMessageListener($serialService, m::mock(ConfigInterface::class), $logger, $store, m::mock(ApiRequest::class));

            $listener->process(new PipeMessage(new JobPipeMessage(killJobId: 1, replyId: $replyId)));
            Coroutine::sleep(0.05);

            $reply = $store->consumeReply($replyId);
            self::assertSame(500, $reply['code']);
            self::assertSame('kill failed', $reply['msg']);
        } finally {
            $store->cleanup(-1);
            if (is_dir($store->getDirectory())) {
                rmdir($store->getDirectory());
            }
        }
    }

    public function testInvalidPayloadFileCallbacksEnvelopedLogId(): void
    {
        $config = new Config();
        $config->setAppName('invalid-payload-test');
        $config->setLogFileDir('/tmp/invalid-payload-' . uniqid('', true));
        $store = new JobPipeFileStore($config);
        try {
            $payloadFile = $store->putPayload('not a serialized message');
            $serialService = m::mock(JobSerialExecutionService::class);
            $serialService->shouldNotReceive('handle');
            $logger = m::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('error')->once();
            $apiRequest = m::mock(ApiRequest::class);
            $apiRequest->shouldReceive('callback')
                ->once()
                ->with(100, 123456, 500, m::type('string'))
                ->andReturnNull();
            $listener = new OnPipeMessageListener($serialService, m::mock(ConfigInterface::class), $logger, $store, $apiRequest);

            $listener->process(new PipeMessage(new JobPipeMessage(
                payloadFile: $payloadFile,
                logId: 100,
                logDateTime: 123456,
            )));
            Coroutine::sleep(0.05);

            self::assertFileDoesNotExist($payloadFile);
        } finally {
            $store->cleanup(-1);
            if (is_dir($store->getDirectory())) {
                rmdir($store->getDirectory());
            }
        }
    }
}
