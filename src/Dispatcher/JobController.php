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
use Hyperf\Utils\Context;
use Hyperf\Utils\Coroutine;
use Hyperf\XxlJob\Application;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Logger\XxlJobHelper;
use Hyperf\XxlJob\Logger\XxlJobLogger;
use Hyperf\XxlJob\Requests\LogRequest;
use Hyperf\XxlJob\Requests\RunRequest;
use Throwable;

class JobController extends BaseJobController
{

    public function run(): ResponseInterface
    {
        $runRequest = RunRequest::create($this->input());
        $this->stdoutLogger->debug('>>>>>>>>>>> xxl-job receive job, jobId:' . $runRequest->getJobId());
        $stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $stdoutLogger->debug(sprintf('>>>>>>>>>>> xxl-job receive job, executorHandler:%s jobId:%s logId:%s', $runRequest->getExecutorHandler(), $runRequest->getJobId(), $runRequest->getLogId()));
        if ($runRequest->getGlueType() != 'BEAN') {
            $message = 'xxl-job the client only supports BEAN';
            $this->stdoutLogger->warning($message);
            return $this->responseFail($message);
        }
        $executorHandler = $runRequest->getExecutorHandler();
        $jobDefinition = $this->application->getJobHandlerDefinitions($executorHandler);

        if (empty($jobDefinition)) {
            $message = 'xxl-job executorHandler:' . $executorHandler . ' class not found!';
            $this->stdoutLogger->warning($message);
            return $this->responseFail($message);
        }
        if (empty($classMethod)) {
            $message = 'xxl-job executorHandler:' . $executorHandler . ' class::method not found!';
            $stdoutLogger->warning($message);
            return $this->responseFail($message);
        }

        $jobInstance = $this->container->get($jobDefinition->getClass());
        if (! method_exists($jobInstance, $jobDefinition->getMethod())) {
            $message = sprintf('xxl-job %s::%s method not exist', $jobDefinition->getClass(), $jobDefinition->getMethod());
            $this->stdoutLogger->error($message);
            return $this->responseFail($message);
        }
        Coroutine::create(function () use ($jobInstance, $jobDefinition, $runRequest) {
            $this->handle($jobInstance, $jobDefinition->getMethod(), $jobDefinition->getInit(), $jobDefinition->getDestroy(), $runRequest);
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

        [$content,$row] = $this->getXxlJobLogger()->getLine($logFile, $logRequest->getFromLineNum());
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

    /**
     * @throws Throwable
     */
    private function handle(object $jobInstance, string $method, string $init, string $destroy, RunRequest $runRequest)
    {
        //set
        Context::set(XxlJobLogger::MARK_JOB_LOG_ID, $runRequest->getLogId());
        Context::set(RunRequest::class, $runRequest);
        //log
        $this->jobExecutorLogger->info('----------- php xxl-job job execute start -----------');
        $this->jobExecutorLogger->info('----------- param:' . $runRequest->getExecutorParams());

        try {
            //init
            if (! empty($init)) {
                $jobInstance->{$init}();
            }

            $jobInstance->{$method}();

            //destroy
            if (! empty($destroy)) {
                $jobInstance->{$destroy}();
            }
            $this->jobExecutorLogger->info('----------- php xxl-job job execute end(finish) -----------');
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            if ($this->container->has(FormatterInterface::class)) {
                $formatter = $this->container->get(FormatterInterface::class);
                $message = $formatter->format($throwable);
                $message = str_replace("\n", '<br>', $message);
            }
            XxlJobHelper::get()->error($message);
            $this->application->service->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $message);
            $this->getXxlJobHelper()->getLogger()->error($message);
            throw $throwable;
        }
        $this->application->service->callback($runRequest->getLogId(), $runRequest->getLogDateTime());
    }
}
