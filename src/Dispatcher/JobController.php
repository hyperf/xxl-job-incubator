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
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Glue\GlueHandlerManager;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\LogRequest;
use Hyperf\XxlJob\Requests\RunRequest;
use Psr\Container\ContainerInterface;

class JobController extends BaseJobController
{
    protected GlueHandlerManager $glueHandlerManager;

    public function __construct(
        ContainerInterface $container,
        StdoutLoggerInterface $stdoutLogger,
        JobExecutorLoggerInterface $jobExecutorLogger
    ) {
        parent::__construct($container, $stdoutLogger, $jobExecutorLogger);
        $this->glueHandlerManager = $container->get(GlueHandlerManager::class);
    }

    public function run(): ResponseInterface
    {
        $runRequest = RunRequest::create($this->input());
        $message = sprintf('Received a XXL-JOB, JobId:%s LogId:%s GlueType:%s', $runRequest->getJobId(), $runRequest->getLogId(), $runRequest->getGlueType());
        if ($runRequest->getGlueType() === GlueEnum::BEAN) {
            $message .= sprintf(' ExecutorHandler:%s', $runRequest->getExecutorHandler());
        }
        $this->stdoutLogger->debug($message);

        try {
            $this->glueHandlerManager->handle($runRequest->getGlueType(), $runRequest);
        } catch (XxlJobException $exception) {
            $this->stdoutLogger->warning($exception->getMessage());
            return $this->responseFail($exception->getMessage());
        }
        return $this->responseSuccess();
    }

    public function log(): ResponseInterface
    {
        $logRequest = LogRequest::create($this->input());

        [$content, $endLine, $isEnd] = $this->jobExecutorLogger->retrieveLog($logRequest->getLogId(), $logRequest->getLogDateTim(), $logRequest->getFromLineNum(), -1);

        if ($endLine <= 0) {
            $data = [
                'code' => 500,
                'msg' => 'Failed to read the log, the file does not exists',
                'content' => [
                    'fromLineNum' => $logRequest->getFromLineNum(),
                    'toLineNum' => $endLine,
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
                'toLineNum' => $endLine,
                'logContent' => $content,
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
        return $this->responseSuccess();
    }

    public function kill(): ResponseInterface
    {
        return $this->responseFail('Not supported');
    }
}
