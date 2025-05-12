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

use Hyperf\XxlJob\Exception\GlueHandlerExecutionException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Requests\RunRequest;
use Symfony\Component\Process\Process;

class ScriptHandler extends AbstractGlueHandler
{
    protected string $scriptDir = BASE_PATH . '/runtime/xxl_job/glue_scripts/';

    protected string $glueType;

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
        $this->jobRun->executeCoroutine($request, function (RunRequest $request) {
            $filePath = $this->generateFilePath($request->getJobId(), $request->getGlueUpdatetime());
            if (! is_file($filePath)) {
                file_put_contents($filePath, $request->getGlueSource());
            }
            $this->executeCmd($filePath, $request);
        });
    }

    protected function generateFilePath(int $logId, int $glueUpdateTime): string
    {
        return $this->scriptDir . $logId . '-' . $glueUpdateTime . $this->getFileSuffix($this->glueType);
    }

    protected function executeCmd(string $filePath, RunRequest $request): void
    {
        $bin = $this->getCmdBin($this->glueType);
        $executorTimeout = $request->getExecutorTimeout();
        $process = new Process([$bin, $filePath, $request->getExecutorParams(), $request->getBroadcastIndex(), $request->getBroadcastTotal()], timeout: $executorTimeout > 0 ? $executorTimeout : null);
        $process->start();
        $filename = $this->jobRun->putProcessInfo($process->getPid(), $request);
        try {
            $process->wait(function ($type, $buffer): void {
                $buffer = trim($buffer);
                if ($type === Process::ERR) {
                    $this->jobExecutorLogger->error($buffer);
                } else {
                    $this->jobExecutorLogger->info($buffer);
                }
            });
        } finally {
            @unlink($filename);
        }
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
