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

namespace Hyperf\XxlJob\Service\Executor;

use Hyperf\XxlJob\Config;
use Hyperf\XxlJob\Exception\XxlJobException;
use Hyperf\XxlJob\Requests\RunRequest;

class JobRunInfoStore
{
    public const TYPE_COROUTINE = 'coroutine';

    public const TYPE_PROCESS = 'process';

    public function __construct(protected Config $config)
    {
    }

    /**
     * 发布一代运行信息；同 jobId 的新 logId 会整体替换旧记录。
     */
    public function put(RunRequest $request, string $executorType, ?int $pid = null): string
    {
        if (! in_array($executorType, [self::TYPE_COROUTINE, self::TYPE_PROCESS], true)) {
            throw new XxlJobException(sprintf('Invalid XXL-JOB executor type: %s.', $executorType));
        }

        $jobId = $request->getJobId();
        $ownerPid = getmypid();
        $requestData = $request->jsonSerialize();
        // cid、Symfony Process 等只在创建它们的进程内有效，不能序列化给其他 worker 使用
        unset($requestData['_extension']);
        $data = [
            'jobId' => $jobId,
            'logId' => $request->getLogId(),
            'executorType' => $executorType,
            'ownerPid' => $ownerPid === false ? null : $ownerPid,
            'ownerStartTime' => $this->readProcessStartTime($ownerPid === false ? null : $ownerPid),
            'pid' => $pid,
            'pidStartTime' => $this->readProcessStartTime($pid),
            'createTime' => time(),
            'runRequest' => $requestData,
        ];

        $this->withLock($jobId, function () use ($jobId, $data): void {
            $this->writeUnlocked($jobId, $data);
        });
        return $this->getFilePath($jobId);
    }

    /**
     * 子进程启动后只补 PID，且必须仍属于同一 logId，防止旧任务覆盖新一代记录。
     */
    public function updatePid(RunRequest $request, int $pid): bool
    {
        $jobId = $request->getJobId();
        $logId = $request->getLogId();
        return $this->withLock($jobId, function () use ($jobId, $logId, $pid): bool {
            $info = $this->get($jobId);
            if (($info['logId'] ?? null) !== $logId) {
                return false;
            }
            $info['pid'] = $pid;
            $info['pidStartTime'] = $this->readProcessStartTime($pid);
            unset($info['filePath']);
            $this->writeUnlocked($jobId, $info);
            return true;
        });
    }

    public function get(int $jobId): array
    {
        $path = $this->getFilePath($jobId);
        if (! is_file($path)) {
            return [];
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            // rename/unlink 的并发窗口中文件可能刚好消失，按无运行记录处理
            return is_file($path) ? $this->invalidInfo($path) : [];
        }
        $info = json_decode($contents, true);
        $required = ['jobId', 'logId', 'executorType', 'ownerPid', 'ownerStartTime', 'pid', 'pidStartTime', 'createTime', 'runRequest'];
        if (! is_array($info)
            || array_diff($required, array_keys($info)) !== []
            || $info['jobId'] !== $jobId
            || ! in_array($info['executorType'], [self::TYPE_COROUTINE, self::TYPE_PROCESS], true)
            || ! is_array($info['runRequest'])) {
            return $this->invalidInfo($path);
        }

        $info['runRequest'] = RunRequest::create($info['runRequest']);
        $info['filePath'] = $path;
        return $info;
    }

    public function remove(int $jobId, int $logId): void
    {
        $this->withLock($jobId, function () use ($jobId, $logId): void {
            $info = $this->get($jobId);
            if (($info['logId'] ?? null) !== $logId) {
                return;
            }
            $path = $this->getFilePath($jobId);
            if (is_file($path) && ! @unlink($path)) {
                throw new XxlJobException(sprintf('Failed to remove XXL-JOB run info file: %s.', $path));
            }
        });
    }

    public function isRun(array $info): bool
    {
        if ($info === []) {
            return false;
        }
        if ($info['executorType'] === self::TYPE_COROUTINE) {
            return $this->isPidAlive($info['ownerPid'], $info['ownerStartTime']);
        }

        // 进程 Bean 会先发布请求、再启动子进程；启动窗口内以属主存活判断
        if ($info['pid'] === null) {
            return $this->isPidAlive($info['ownerPid'], $info['ownerStartTime']);
        }
        return $this->isPidAlive($info['pid'], $info['pidStartTime']);
    }

    public function hasLiveProcess(array $info): bool
    {
        $pid = $info['pid'] ?? null;
        if (! is_int($pid) || $pid <= 0) {
            return false;
        }
        if ($this->readProcessStartTime($pid) !== null) {
            return true;
        }
        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    public function getFilePath(int $jobId): string
    {
        return $this->config->getLogFileDir() . sprintf('jobId_%s.info', $jobId);
    }

    protected function writeUnlocked(int $jobId, array $info): void
    {
        $path = $this->getFilePath($jobId);
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB run info directory: %s.', $directory));
        }
        $json = json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new XxlJobException('Failed to encode XXL-JOB run info.');
        }

        $temporary = tempnam($directory, '.xxl_job_info_');
        if ($temporary === false) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB run info temporary file in %s.', $directory));
        }
        try {
            if (file_put_contents($temporary, $json) !== strlen($json) || ! @rename($temporary, $path)) {
                throw new XxlJobException(sprintf('Failed to publish XXL-JOB run info file: %s.', $path));
            }
        } finally {
            // rename 成功后临时路径已不存在；失败时不能留下半截文件
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    protected function withLock(int $jobId, callable $callback): mixed
    {
        $path = $this->getFilePath($jobId);
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new XxlJobException(sprintf('Failed to create XXL-JOB run info directory: %s.', $directory));
        }
        $handle = @fopen($path . '.lock', 'c');
        if ($handle === false) {
            throw new XxlJobException(sprintf('Failed to open XXL-JOB run info lock for JobId:%s.', $jobId));
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new XxlJobException(sprintf('Failed to lock XXL-JOB run info for JobId:%s.', $jobId));
            }
            try {
                // 替换、核对 logId 后删除必须共用一把锁，否则存在 TOCTOU 窗口
                return $callback();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }

    protected function isPidAlive(?int $pid, ?int $expectedStartTime): bool
    {
        if ($pid === null || $pid <= 0) {
            return false;
        }
        $actualStartTime = $this->readProcessStartTime($pid);
        if ($expectedStartTime !== null || $actualStartTime !== null) {
            // 同时校验 PID 与启动标识，防止退出后 PID 被复用而误判、误杀
            return $expectedStartTime !== null && $actualStartTime === $expectedStartTime;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        return false;
    }

    protected function readProcessStartTime(?int $pid): ?int
    {
        if ($pid === null || $pid <= 0 || ! is_file('/proc/' . $pid . '/stat')) {
            return null;
        }
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        if ($stat === false) {
            return null;
        }
        $closing = strrpos($stat, ')');
        if ($closing === false) {
            return null;
        }
        // comm 可能含空格和括号，必须从最后一个右括号后解析；state 是第 3 列，starttime 是第 22 列
        $fields = explode(' ', trim(substr($stat, $closing + 2)));
        if (($fields[0] ?? null) === 'Z') {
            return null;
        }
        return isset($fields[19]) && is_numeric($fields[19]) ? (int) $fields[19] : null;
    }

    protected function invalidInfo(string $path): array
    {
        throw new XxlJobException(sprintf('Invalid XXL-JOB run info file: %s.', $path));
    }
}
