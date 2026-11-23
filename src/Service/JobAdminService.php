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

namespace Hyperf\XxlJob\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Hyperf\Codec\Json;
use Hyperf\XxlJob\ApiRequest;
use Hyperf\XxlJob\Exception\XxlJobException;
use Throwable;

/**
 * 调度中心「任务管理」OpenAPI 客户端（xxl-job >= 3.5.0）。
 *
 * 这组接口由调度中心提供给三方集成系统使用，官方执行器本身不调用；
 * 请求头 XXL-JOB-ACCESS-TOKEN / XXL-JOB-APPNAME 由 ApiRequest 统一注入。
 */
class JobAdminService
{
    public function __construct(protected ApiRequest $apiRequest)
    {
    }

    /**
     * @return int 新任务 ID
     * @throws XxlJobException
     */
    public function addJob(array $job): int
    {
        // jobGroup 是执行器组主键，admin 会校验其存在性，这里先做最小类型校验避免无效请求
        $job['jobGroup'] = $this->normalizePositiveId($job['jobGroup'] ?? null, 'jobGroup');
        // admin 会对 executorHandler 无条件 trim()，GLUE 任务也必须传字符串
        $job['executorHandler'] = $this->normalizeString($job['executorHandler'] ?? '', 'executorHandler');
        $job['scheduleConf'] = $this->normalizeString($job['scheduleConf'] ?? '', 'scheduleConf');

        $result = $this->call('/api/addJob', $job);
        // 官方文档写的是 content，实际实现返回 data，这里以源码为准
        $jobId = (int) ($result['data'] ?? 0);
        if ($jobId <= 0) {
            throw new XxlJobException('XXL-JOB addJob response does not contain a valid job id.');
        }
        return $jobId;
    }

    /**
     * @throws XxlJobException
     */
    public function updateJob(array $job): void
    {
        $job['id'] = $this->normalizePositiveId($job['id'] ?? null, 'id');
        // 官方 update DTO 不含 jobGroup，admin 只在 jobGroup > 0 时才校验，直接移除避免误传
        unset($job['jobGroup']);
        // admin 中 executorHandler.trim() 与 scheduleConf.equals() 都可能因 null 抛异常
        $job['executorHandler'] = $this->normalizeString($job['executorHandler'] ?? '', 'executorHandler');
        $job['scheduleConf'] = $this->normalizeString($job['scheduleConf'] ?? '', 'scheduleConf');

        $this->call('/api/updateJob', $job);
    }

    /**
     * @throws XxlJobException
     */
    public function removeJob(int $id): void
    {
        $this->call('/api/removeJob', ['id' => $this->assertPositiveId($id)]);
    }

    /**
     * @throws XxlJobException
     */
    public function startJob(int $id): void
    {
        $this->call('/api/startJob', ['id' => $this->assertPositiveId($id)]);
    }

    /**
     * @throws XxlJobException
     */
    public function stopJob(int $id): void
    {
        $this->call('/api/stopJob', ['id' => $this->assertPositiveId($id)]);
    }

    /**
     * @throws XxlJobException
     */
    public function triggerJob(int $id, string $executorParam = '', string $addressList = ''): void
    {
        $body = ['id' => $this->assertPositiveId($id)];
        // 空参数由 admin 按默认逻辑处理（addressList 为空时从注册中心取地址）
        if ($executorParam !== '') {
            $body['executorParam'] = $executorParam;
        }
        if ($addressList !== '') {
            $body['addressList'] = $addressList;
        }
        $this->call('/api/triggerJob', $body);
    }

    /**
     * @return array 解码后的完整响应（含 code/msg/data）
     * @throws XxlJobException
     */
    protected function call(string $uri, array $body): array
    {
        try {
            $response = $this->apiRequest->request('POST', $uri, [RequestOptions::JSON => $body]);
        } catch (GuzzleException $exception) {
            throw new XxlJobException(sprintf('XXL-JOB admin API %s request failed: %s', $uri, $exception->getMessage()), 0, $exception);
        }

        $content = (string) $response->getBody();
        if ($content === '') {
            throw new XxlJobException(sprintf('XXL-JOB admin API %s returned an empty response.', $uri));
        }

        try {
            $result = Json::decode($content);
        } catch (Throwable $exception) {
            // 反向代理/登录跳转可能返回 HTML，截取片段便于定位
            throw new XxlJobException(sprintf(
                'XXL-JOB admin API %s returned invalid JSON: %s; body: %s',
                $uri,
                $exception->getMessage(),
                mb_substr($content, 0, 200)
            ), 0, $exception);
        }

        if (! is_array($result)) {
            throw new XxlJobException(sprintf('XXL-JOB admin API %s returned an unexpected payload.', $uri));
        }

        // admin 的业务失败也是 HTTP 200，必须判断响应体 code
        $code = (int) ($result['code'] ?? 0);
        if ($code !== 200) {
            $message = (string) ($result['msg'] ?? '');
            if ($code === 502) {
                $message = 'rate limited (100 requests / 30 seconds): ' . $message;
            }
            throw new XxlJobException(sprintf('XXL-JOB admin API %s failed with code %d: %s', $uri, $code, $message));
        }

        return $result;
    }

    /**
     * @throws XxlJobException
     */
    protected function normalizePositiveId(mixed $value, string $field): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $value = (int) $value;
            if ($value > 0) {
                return $value;
            }
        }
        throw new XxlJobException(sprintf('The xxl-job %s must be a positive integer.', $field));
    }

    /**
     * @throws XxlJobException
     */
    protected function assertPositiveId(int $id): int
    {
        if ($id <= 0) {
            throw new XxlJobException('The xxl-job job id must be a positive integer.');
        }
        return $id;
    }

    /**
     * @throws XxlJobException
     */
    protected function normalizeString(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new XxlJobException(sprintf('The xxl-job %s must be a string.', $field));
    }
}
