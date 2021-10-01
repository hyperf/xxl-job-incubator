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
namespace Hyperf\XxlJob\Glue\Handlers;

use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\JobHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;
use Throwable;

class ScriptHandler extends AbstractGlueHandler
{
    protected string $scriptDir = BASE_PATH . '/runtime/xxl_job/glue_scripts/';

    protected string $glueType;

    protected Config $config;

    public function __construct(
        ContainerInterface $container,
        JobHandlerManager $jobHandlerManager,
        ApiRequest $apiRequest,
        JobExecutorLoggerInterface $jobExecutorLogger
    ) {
        parent::__construct($container, $jobHandlerManager, $apiRequest, $jobExecutorLogger);
        $this->config = $container->get(Config::class);
    }

    public function handle(RunRequest $request)
    {
        if (! is_dir($this->scriptDir)) {
            mkdir($this->scriptDir, 0777, true);
        }
        $this->glueType = $request->getGlueType();
        if (! GlueEnum::isScript($this->glueType)) {
            return;
        }
        if (! $this->config->getAccessToken()) {
            throw new GlueHandlerExecutionException('No configuration value of AccessToken, cannot handle ALL Script Glue Type');
        }
        JobContext::runJob($request, function (RunRequest $request) {
            try {
                $this->jobExecutorLogger->info(sprintf('Beginning, with params: %s', $request->getExecutorParams() ?: '[NULL]'));

                $filePath = $this->generateFilePath($request->getJobId(), $request->getGlueUpdatetime());
                if (! is_file($filePath)) {
                    file_put_contents($filePath, $request->getGlueSource());
                }
                // Set the parameter value to '' wher the value is empty
                $params = [
                    // ExecutorParams is string, use ?:
                    $request->getExecutorParams() ?: "''",
                    // Index and Total is int, use ??
                    $request->getBroadcastIndex() ?? "''",
                    $request->getBroadcastTotal() ?? "''",
                ];
                $output = $this->executeCmd($filePath, $params);
                $this->jobExecutorLogger->info($output);

                $this->jobExecutorLogger->info('Finished');
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime());
            } catch (Throwable $throwable) {
                $message = $throwable->getMessage();
                if ($this->container->has(FormatterInterface::class)) {
                    $formatter = $this->container->get(FormatterInterface::class);
                    $message = $formatter->format($throwable);
                    $message = str_replace(PHP_EOL, '<br>', $message);
                }
                $this->apiRequest->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
                $this->jobExecutorLogger->error($message);
                throw $throwable;
            }
        });
    }

    protected function generateFilePath(int $logId, int $glueUpdateTime): string
    {
        return $this->scriptDir . $logId . '-' . $glueUpdateTime . $this->getFileSuffix($this->glueType);
    }

    protected function executeCmd(string $filePath, array $arguments): string
    {
        $bin = $this->getCmdBin($this->glueType);
        $cmd = sprintf('%s %s %s', $bin, $filePath, implode(' ', $arguments));
        return trim(shell_exec("{$cmd} 2>&1"));
    }

    protected function getCmdBin(string $type): string
    {
        return GlueEnum::getCmd($type);
    }

    protected function getFileSuffix(string $type): string
    {
        return GlueEnum::getSuffix($type);
    }
}
