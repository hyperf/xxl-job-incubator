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

namespace HyperfTest\XxlJob\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\XxlJob\Command\JobAdminCommand;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Service\JobAdminService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 * @covers \Hyperf\XxlJob\Command\JobAdminCommand
 */
class JobAdminCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testCommandName(): void
    {
        $command = new JobAdminCommand(m::mock(JobAdminService::class));

        $this->assertSame('xxl-job:job', $command->getName());
    }

    public function testAddWithJson(): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive('addJob')
            ->once()
            ->with(['jobGroup' => 1, 'name' => 'demo'])
            ->andReturn(42);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], [
            'json' => '{"jobGroup":1,"name":"demo"}',
        ]);

        $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
        $this->assertStringContainsString('Job created, id: 42', $output->fetch());
    }

    public function testAddWithFile(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'xxl-job-');
        file_put_contents($file, '{"jobGroup":2,"name":"from-file"}');

        try {
            $service = m::mock(JobAdminService::class);
            $service->shouldReceive('addJob')
                ->once()
                ->with(['jobGroup' => 2, 'name' => 'from-file'])
                ->andReturn(43);

            [$command, $output] = $this->createCommand($service, ['action' => 'add'], ['file' => $file]);

            $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
            $this->assertStringContainsString('Job created, id: 43', $output->fetch());
        } finally {
            @unlink($file);
        }
    }

    public function testUpdateMergesIdIntoPayload(): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive('updateJob')
            ->once()
            ->with(['id' => 5, 'name' => 'demo'])
            ->andReturnNull();

        [$command, $output] = $this->createCommand($service, ['action' => 'update'], [
            'id' => '5',
            'json' => '{"id":1,"name":"demo"}',
        ]);

        $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
        $this->assertStringContainsString('Job 5 updated.', $output->fetch());
    }

    /**
     * @dataProvider simpleActionProvider
     */
    public function testSimpleActions(string $action, string $method): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive($method)->once()->with(7)->andReturnNull();

        [$command, $output] = $this->createCommand($service, ['action' => $action], ['id' => '7']);

        $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
        $this->assertStringContainsString('Job 7', $output->fetch());
    }

    public static function simpleActionProvider(): array
    {
        return [
            ['remove', 'removeJob'],
            ['start', 'startJob'],
            ['stop', 'stopJob'],
        ];
    }

    public function testTriggerPassesOptions(): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive('triggerJob')->once()->with(7, '{"k":"v"}', 'http://127.0.0.1:9999/')->andReturnNull();

        [$command, $output] = $this->createCommand($service, ['action' => 'trigger'], [
            'id' => '7',
            'executor-param' => '{"k":"v"}',
            'address-list' => 'http://127.0.0.1:9999/',
        ]);

        $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
        $this->assertStringContainsString('Job 7 triggered.', $output->fetch());
    }

    public function testTriggerDefaultsToEmptyOptions(): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive('triggerJob')->once()->with(7, '', '')->andReturnNull();

        [$command] = $this->createCommand($service, ['action' => 'trigger'], ['id' => '7']);

        $this->assertSame(HyperfCommand::SUCCESS, $command->handle());
    }

    public function testUnknownActionFails(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'unknown'], []);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('Unknown action', $output->fetch());
    }

    public function testMissingPayloadOptionsFail(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], []);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('exactly one of --json or --file', $output->fetch());
    }

    public function testBothPayloadOptionsFail(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], [
            'json' => '{}',
            'file' => '/tmp/job.json',
        ]);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('exactly one of --json or --file', $output->fetch());
    }

    public function testUnreadableFileFails(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], [
            'file' => '/path/does/not/exist.json',
        ]);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('not readable', $output->fetch());
    }

    public function testInvalidJsonFails(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], ['json' => 'not-json']);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('Invalid JSON payload', $output->fetch());
    }

    public function testJsonListFails(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], ['json' => '[1,2]']);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('must be an object', $output->fetch());
    }

    public function testInvalidIdFails(): void
    {
        $service = m::mock(JobAdminService::class);

        [$command, $output] = $this->createCommand($service, ['action' => 'remove'], ['id' => '0']);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('positive integer', $output->fetch());
    }

    public function testServiceExceptionIsRendered(): void
    {
        $service = m::mock(JobAdminService::class);
        $service->shouldReceive('addJob')->once()->andThrow(new XxlJobException('admin rejected the request'));

        [$command, $output] = $this->createCommand($service, ['action' => 'add'], ['json' => '{"jobGroup":1}']);

        $this->assertSame(HyperfCommand::FAILURE, $command->handle());
        $this->assertStringContainsString('admin rejected the request', $output->fetch());
    }

    /**
     * @return array{0: JobAdminCommand, 1: BufferedOutput}
     */
    private function createCommand(JobAdminService $service, array $arguments, array $options): array
    {
        $command = new JobAdminCommand($service);

        $input = m::mock(InputInterface::class);
        $input->shouldReceive('getArgument')->andReturnUsing(static fn (string $name) => $arguments[$name] ?? null);
        $input->shouldReceive('getOption')->andReturnUsing(static fn (string $name) => $options[$name] ?? null);

        $output = new BufferedOutput();
        $this->inject($command, 'input', $input);
        $this->inject($command, 'output', $output);

        return [$command, $output];
    }

    private function inject(object $command, string $property, object $value): void
    {
        $ref = new ReflectionClass(HyperfCommand::class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($command, $value);
    }
}
