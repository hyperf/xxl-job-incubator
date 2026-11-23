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

use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Requests\RunRequest;
use Hyperf\XxlJob\Service\Executor\JobRunContent;
use Symfony\Component\Process\Process;
use Throwable;

class ScriptHandler extends AbstractGlueHandler
{
    protected string $glueType;

    public function handle(RunRequest $request)
    {
        if (! is_dir($this->getScriptDir())) {
            mkdir($this->getScriptDir(), 0755, true);
        }
        $this->glueType = $request->getGlueType();
        if (! GlueEnum::isScript($this->glueType)) {
            return;
        }
        $this->jobExecutorCoroutine->executeCoroutine($request, function (RunRequest $request) {
            $filePath = $this->generateFilePath($request->getJobId(), $request->getGlueUpdatetime());
            if (! is_file($filePath)) {
                file_put_contents($filePath, $request->getGlueSource());
            }
            $this->executeCmd($filePath, $request);
        });
    }

    protected function generateFilePath(int $logId, int $glueUpdateTime): string
    {
        return $this->getScriptDir() . $logId . '-' . $glueUpdateTime . $this->getFileSuffix($this->glueType);
    }

    protected function getScriptDir(): string
    {
        return dirname($this->config->getLogFileDir(), 1) . '/glue_scripts/';
    }

    protected function executeCmd(string $filePath, RunRequest $request): void
    {
        $bin = $this->getCmdBin($this->glueType);
        $executorTimeout = $request->getExecutorTimeout();
        $process = new Process([$bin, $filePath, $request->getExecutorParams(), $request->getBroadcastIndex(), $request->getBroadcastTotal()], timeout: $executorTimeout > 0 ? $executorTimeout : null);
        try {
            $process->start();
            $request->setExtension('process', $process);
            $this->jobExecutorProcess->putJobFileInfo($process->getPid(), $request);
            $process->wait(function ($type, $buffer): void {
                $buffer = trim($buffer);
                if ($type === Process::ERR) {
                    $this->jobExecutorLogger->error($buffer);
                } else {
                    $this->jobExecutorLogger->info($buffer);
                }
            });
            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput());
                throw new XxlJobException($message !== '' ? $message : sprintf('Script exited with code %s.', $process->getExitCode() ?? 'unknown'));
            }
        } catch (Throwable $throwable) {
            try {
                // info 发布失败时子进程可能仍在运行，必须先回收再把异常交给上层补 500
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            } catch (Throwable $stopError) {
                $this->stdoutLogger->error($stopError->getMessage());
            }
            throw $throwable;
        } finally {
            try {
                $this->jobExecutorProcess->removeJobFileInfo($request->getJobId(), $request->getLogId());
            } finally {
                JobRunContent::remove($request->getJobId(), $request->getLogId());
            }
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
