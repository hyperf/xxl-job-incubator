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

namespace HyperfTest\XxlJob;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobExecutorCoroutine;
use Hyperf\XxlJob\Service\Executor\JobExecutorProcess;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use RuntimeException;
use Swoole\Coroutine;

/**
 * @internal
 * @covers \Hyperf\XxlJob\ApiRequest
 * @covers \Hyperf\XxlJob\JobCallbackGuard
 */
class CallbackDeduplicationTest extends TestCase
{
    private Config $config;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xxl_job_callback_' . uniqid('', true);
        mkdir($this->directory, 0755, true);
        $this->config = new Config();
        $this->config->setLogFileDir($this->directory . '/logs/');
    }

    protected function tearDown(): void
    {
        m::close();
        $ref = new ReflectionClass(JobRunContent::class);
        foreach (['content', 'channels'] as $name) {
            $property = $ref->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
        $remove = function (string $path) use (&$remove): void {
            if (! is_dir($path)) {
                return;
            }
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
                $file->isDir() ? $remove($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($path);
        };
        $remove($this->directory);
    }

    public function testCallbackIsDeduplicatedAcrossApiInstances(): void
    {
        $response = m::mock(ResponseInterface::class);
        $first = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $first->shouldReceive('request')->once()->andReturn($response);
        $second = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $second->shouldNotReceive('request');

        self::assertSame($response, $first->callback(100, 123456, 500, 'first'));
        self::assertNull($second->callback(100, 123456, 500, 'duplicate'));
    }

    public function testFailedCallbackCanBeRetried(): void
    {
        $response = m::mock(ResponseInterface::class);
        $attempts = 0;
        $api = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $api->shouldReceive('request')->twice()->andReturnUsing(static function () use (&$attempts, $response) {
            ++$attempts;
            if ($attempts === 1) {
                throw new RuntimeException('admin unavailable');
            }
            return $response;
        });

        try {
            $api->callback(100, 123456, 500, 'failure');
            self::fail('Expected the first callback attempt to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('admin unavailable', $exception->getMessage());
        }
        self::assertSame($response, $api->callback(100, 123456, 500, 'retry'));
        self::assertNull($api->callback(100, 123456, 500, 'duplicate'));
        self::assertSame(2, $attempts);
    }

    public function testBatchFailureBlocksLateSuccessCallback(): void
    {
        $api = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $api->shouldReceive('request')->once()->andReturn(m::mock(ResponseInterface::class));

        $api->multipleCallback([
            ['logId' => 100, 'logDateTim' => 123456],
        ], 500, 'dispatcher shutdown');
        $lateResult = $api->callback(100, 123456, 200, 'late success');

        self::assertNull($lateResult);
    }

    public function testCoroutineKillKeepsOneDedicatedCallback(): void
    {
        $api = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $api->shouldReceive('request')
            ->once()
            ->with('POST', '/api/callback', m::on(static function (array $options): bool {
                return $options['json'][0]['handleMsg'] === 'scheduling center kill job. [job running, killed]';
            }))
            ->andReturn(m::mock(ResponseInterface::class));
        $executor = new JobExecutorCoroutine(
            $this->createContainer(),
            m::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing(),
            $api,
            m::mock(JobExecutorLoggerInterface::class)->shouldIgnoreMissing(),
            $this->config,
            $this->createEventDispatcher(),
        );
        $request = $this->createRunRequest(1);
        JobRunContent::setJobId(1, $request);

        $executor->run($request, static function (): void {
            Coroutine::sleep(3);
        });
        Coroutine::sleep(2.5);

        self::assertFalse(JobRunContent::has(1));
        $this->addToAssertionCount(1);
    }

    public function testParentSkipsFailureCallbackAfterChildReported(): void
    {
        $autoload = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $childLog = $this->directory . '/child.log';
        $code = sprintf(
            'require %s; ! defined("BASE_PATH") && define("BASE_PATH", %s); '
            . '$config = new \Hyperf\XxlJob\Config(); $config->setLogFileDir(%s); '
            . '$api = new class($config) extends \Hyperf\XxlJob\ApiRequest { '
            . 'public function request(string $method, string $uri, array $options = []): \Psr\Http\Message\ResponseInterface { '
            . 'file_put_contents(%s, "child-callback", FILE_APPEND); return new \GuzzleHttp\Psr7\Response(); '
            . '} }; $api->callback(100, 123456, 500, "child failed"); exit(1);',
            var_export($autoload, true),
            var_export($this->directory, true),
            var_export($this->config->getLogFileDir(), true),
            var_export($childLog, true),
        );
        $this->config->setStartCommand([PHP_BINARY, '-r', $code]);
        $api = m::mock(ApiRequest::class, [$this->config])->makePartial();
        $api->shouldNotReceive('request');
        $executor = new JobExecutorProcess(
            $this->createContainer(),
            m::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing(),
            $api,
            m::mock(JobExecutorLoggerInterface::class)->shouldIgnoreMissing(),
            $this->config,
            $this->createEventDispatcher(),
        );
        $request = $this->createRunRequest(0);
        JobRunContent::setJobId(1, $request);

        $executor->executeCommand($request);
        $this->waitForCompletion();

        self::assertSame('child-callback', file_get_contents($childLog));
        self::assertFileDoesNotExist($this->config->getLogFileDir() . 'jobId_1.info');
    }

    private function createContainer(): ContainerInterface
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->andReturnFalse();
        return $container;
    }

    private function createEventDispatcher(): EventDispatcherInterface
    {
        $events = m::mock(EventDispatcherInterface::class);
        $events->shouldReceive('dispatch')->andReturnUsing(static fn (object $event): object => $event);
        return $events;
    }

    private function createRunRequest(int $timeout): RunRequest
    {
        return RunRequest::create([
            'jobId' => 1,
            'logId' => 100,
            'logDateTime' => 123456,
            'executorTimeout' => $timeout,
            'executorHandler' => 'demo',
        ]);
    }

    private function waitForCompletion(): void
    {
        $deadline = microtime(true) + 6;
        while (JobRunContent::has(1) && microtime(true) < $deadline) {
            Coroutine::sleep(0.01);
        }
        self::assertFalse(JobRunContent::has(1));
    }
}
