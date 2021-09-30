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
namespace Hyperf\XxlJob\Dispatcher;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\XxlJob\JobContext;
use Hyperf\XxlJob\JobDefinition;
use Hyperf\XxlJob\Requests\LogRequest;
use Hyperf\XxlJob\Requests\RunRequest;
use Throwable;

class JobController extends BaseJobController
{

    public function run(): ResponseInterface
    {
        $runRequest = RunRequest::create($this->input());
        $this->stdoutLogger->debug(sprintf('Received a XXL-JOB, JobHandler: %s JobId: %s LogId: %s', $runRequest->getExecutorHandler(), $runRequest->getJobId(), $runRequest->getLogId()));
        if ($runRequest->getGlueType() !== 'BEAN') {
            $message = 'The xxl-job client only runs in BEAN mode';
            $this->stdoutLogger->warning($message);
            return $this->responseFail($message);
        }
        $executorHandler = $runRequest->getExecutorHandler();
        $jobDefinition = $this->application->getJobHandlerDefinitions($executorHandler);

        if (empty($jobDefinition) || ! method_exists($jobDefinition->getClass(), $jobDefinition->getMethod())) {
            $message = sprintf('The definition of executor handler %s is invalid.', $executorHandler);
            $this->stdoutLogger->warning($message);
            return $this->responseFail($message);
        }

        JobContext::runJob($jobDefinition, $runRequest, function (JobDefinition $jobDefinition, RunRequest $request) {
            try {
                $this->jobExecutorLogger->info(sprintf('is beginning, with params: %s', $request->getExecutorParams() ?: '[NULL]'));

                $jobInstance = $this->container->get($jobDefinition->getClass());
                $init = $jobDefinition->getInit();
                $method = $jobDefinition->getMethod();
                $destroy = $jobDefinition->getDestroy();

                if (! empty($init) && method_exists($jobInstance, $init)) {
                    $jobInstance->{$init}($request);
                }

                $jobInstance->{$method}($request);

                if (! empty($destroy) && method_exists($jobInstance, $destroy)) {
                    $jobInstance->{$destroy}($request);
                }
                $this->jobExecutorLogger->info('is finished');
                $this->application->service->callback($request->getLogId(), $request->getLogDateTime());
            } catch (Throwable $throwable) {
                $message = $throwable->getMessage();
                if ($this->container->has(FormatterInterface::class)) {
                    $formatter = $this->container->get(FormatterInterface::class);
                    $message = $formatter->format($throwable);
                    $message = str_replace("\n", '<br>', $message);
                }
                $this->application->service->callback($request->getLogId(), $request->getLogDateTime(), 500, $message);
                $this->jobExecutorLogger->error($message);
                throw $throwable;
            }
        });
        return $this->responseSuccess();
    }

    public function log(): ResponseInterface
    {
        $logRequest = LogRequest::create($this->input());

        $logFile = $this->getXxlJobHelper()->getLogFilename();

        if (! file_exists($logFile)) {
            $data = [
                'code' => 200,
                'msg' => null,
                'content' => [
                    'fromLineNum' => $logRequest->getFromLineNum(),
                    'toLineNum' => 0,
                    'logContent' => 'readLog fail, logFile not exists',
                    'isEnd' => true,
                ],
            ];
            return $this->response($data);
        }

        [$content, $row] = $this->getXxlJobLogger()->getLine($logFile, $logRequest->getFromLineNum());
        $data = [
            'code' => 200,
            'msg' => null,
            'content' => [
                'fromLineNum' => $logRequest->getFromLineNum(),
                'toLineNum' => $row,
                'logContent' => $content,
                'isEnd' => false,
            ],
        ];

        return $this->response($data);
    }

    public function beat(): ResponseInterface
    {
        return $this->responseSuccess();
    }

    public function idleBeat(): ResponseInterface
    {
        return $this->responseSuccess();
    }

    public function kill(): ResponseInterface
    {
        return $this->responseFail('Not supported');
    }

}
