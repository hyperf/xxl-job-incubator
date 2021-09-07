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
        $stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $stdoutLogger->debug('>>>>>>>>>>> xxl-job receive job, jobId:' . $runRequest->getJobId());
        if ($runRequest->getGlueType() != 'BEAN') {
            $message = 'xxl-job the client only supports BEAN';
            $stdoutLogger->warning($message);
            return $this->resultJson($this->fail['msg'] = $message);
        }
        $executorHandler = $runRequest->getExecutorHandler();
        $classMethod = Application::getJobHandlers($executorHandler);

        if (empty($classMethod)) {
            $message = 'xxl-job executorHandler:' . $executorHandler . ' class not found!';
            $stdoutLogger->warning($message);
            return $this->resultJson($this->fail['msg'] = $message);
        }

        $class = $classMethod['class'];
        $method = $classMethod['method'];
        $init = $classMethod['init'];
        $destroy = $classMethod['destroy'];
        $classObj = $this->container->get($class);
        if (! method_exists($classObj, $method)) {
            $message = sprintf('xxl-job %s::%s method not exist', $class, $method);
            $stdoutLogger->error($message);
            return $this->resultJson($this->fail['msg'] = $message);
        }
        Coroutine::create(function () use ($classObj, $method, $init, $destroy, $runRequest) {
            $this->handle($classObj, $method, $init, $destroy, $runRequest);
        });
        return $this->resultJson($this->success);
    }

    public function log(): ResponseInterface
    {
        $logRequest = LogRequest::create($this->input());

        $logFile = XxlJobHelper::logFile();

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
            return $this->resultJson($data);
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

        return $this->resultJson($data);
    }

    public function beat(): ResponseInterface
    {
        return $this->resultJson($this->success);
    }

    public function idleBeat(): ResponseInterface
    {
        return $this->resultJson($this->success);
    }

    public function kill(): ResponseInterface
    {
        return $this->resultJson($this->fail['msg'] = 'not supported !');
    }

    /**
     * @param mixed $jobHandlerObj
     * @param mixed $method
     * @param mixed $init
     * @param mixed $destroy
     * @throws Throwable
     */
    private function handle($jobHandlerObj, $method, $init, $destroy, RunRequest $runRequest)
    {
        //set
        Context::set(XxlJobLogger::MARK_JOB_LOG_ID, $runRequest->getLogId());
        Context::set(RunRequest::class, $runRequest);
        /*$server = $this->serverFactory->getServer()->getServer();
        $workerId = $server->getWorkerId();
        $cid = Coroutine::id();
        XxlJobHelper::log("----------- workId:{$workerId} cid:{$cid} -----------");*/
        //log
        XxlJobHelper::log('----------- php xxl-job job execute start -----------');
        XxlJobHelper::log('----------- param:' . $runRequest->getExecutorParams());

        try {
            //init
            if (! empty($init)) {
                $jobHandlerObj->{$init}();
            }

            $jobHandlerObj->{$method}();

            //destroy
            if (! empty($destroy)) {
                $jobHandlerObj->{$destroy}();
            }
            XxlJobHelper::log('----------- php xxl-job job execute end(finish) -----------');
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            if ($this->container->has(FormatterInterface::class)) {
                $formatter = $this->container->get(FormatterInterface::class);
                $message = $formatter->format($throwable);
                $message = str_replace("\n", '<br>', $message);
            }
            XxlJobHelper::get()->error($message);
            $this->app->service->callback($runRequest->getLogId(), $runRequest->getLogDateTime(), 500, $message);
            throw $throwable;
        }
        $this->app->service->callback($runRequest->getLogId(), $runRequest->getLogDateTime());
    }
}
