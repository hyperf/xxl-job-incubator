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

use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Requests\LogRequest;
use Hyperf\XxlJob\Requests\RunRequest;
use Throwable;

class JobController extends BaseController
{
    public function run(): ResponseInterface
    {
        $runRequest = RunRequest::create($this->input());
        $message = sprintf('Received a XXL-JOB, JobId:%s LogId:%s GlueType:%s', $runRequest->getJobId(), $runRequest->getLogId(), $runRequest->getGlueType());
        if ($runRequest->getGlueType() === GlueEnum::BEAN) {
            $message .= sprintf(' ExecutorHandler:%s', $runRequest->getExecutorHandler());
        }
        $this->stdoutLogger->debug($message);

        try {
            $this->jobService->executorBlockStrategy($runRequest);
        } catch (XxlJobException $exception) {
            $this->stdoutLogger->warning($exception->getMessage());
            return $this->responseFail($exception->getMessage());
        }
        return $this->responseSuccess();
    }

    public function log(): ResponseInterface
    {
        $logRequest = LogRequest::create($this->input());

        $logContent = $this->jobExecutorLogger->retrieveLog($logRequest->getLogId(), $logRequest->getLogDateTim(), $logRequest->getFromLineNum(), -1);

        if ($logContent->getEndLine() <= 0) {
            $data = [
                'code' => 500,
                'msg' => 'Failed to read the log, the file does not exists',
                'content' => [
                    'fromLineNum' => $logRequest->getFromLineNum(),
                    'toLineNum' => $logContent->getEndLine(),
                    'logContent' => '',
                    'isEnd' => true,
                ],
            ];
            return $this->response($data);
        }

        $data = [
            'code' => 200,
            'msg' => null,
            'content' => [
                'fromLineNum' => $logRequest->getFromLineNum(),
                'toLineNum' => $logContent->getEndLine(),
                'logContent' => $logContent->getContent(),
                // The XXL-JOB Server will not rolling load the log content even isEnd returns false, so make sure all log content has been retrieved.
                'isEnd' => true,
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
        $jobId = $this->input()['jobId'];
        $isRun = $this->jobService->isRun($jobId);
        if ($isRun) {
            return $this->responseFail('job thread is running or has trigger queue.');
        }
        return $this->responseSuccess();
    }

    public function kill(): ResponseInterface
    {
        $jobId = $this->input()['jobId'];
        try {
            $this->jobService->send(killJobId: $jobId);
            $bool = $this->jobService->kill($jobId, 0, 'Job toStop, stopReason:scheduling center kill job.');
            if (! $bool) {
                return $this->responseFail('job cannot be completely killed. Please try again');
            }

            $this->stdoutLogger->info("XXL-JOB, kill the jobId:{$jobId} successfully");
            return $this->responseSuccess();
        } catch (Throwable $throwable) {
            $this->stdoutLogger->error($throwable);
            return $this->responseFail($throwable->getMessage());
        }
        // return $this->responseFail('Not supported');
    }
}
