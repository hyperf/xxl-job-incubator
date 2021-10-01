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
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\Requests\RunRequest;
use Throwable;

class PHPScriptHandler extends AbstractGlueHandler
{
    protected string $scriptDir = BASE_PATH . '/runtime/xxl_job/glue_scripts/';

    public function handle(RunRequest $request)
    {
        if (! is_dir($this->scriptDir)) {
            mkdir($this->scriptDir, 0777, true);
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
        return $this->scriptDir . $logId . '-' . $glueUpdateTime . '.php';
    }

    protected function executeCmd(string $filePath, array $arguments): string
    {
        $cmd = sprintf('php %s %s', $filePath, implode(' ', $arguments));
        return trim(shell_exec("{$cmd} 2>&1"));
    }
}
