## PHP XxlJob Job Executor

此为 xxl-job 的 PHP 版本的任务执行器(Job Executor)，特别适配于 Hyperf 框架，其余框架尚未验证适配性。

## 优点

- 分布式任务调度平台
- 任务可以随时关闭与开启
- 日志可通过服务端查看


## 注意

> xxl-job 服务端版本 >= 2.2.0

> 不能取消正在执行的任务
## 安装

```
composer require hyperf/xxl-job-incubator
```

## 使用

#### 发布配置文件

```bash
php bin/hyperf.php vendor:publish hyperf/xxl-job-incubator
```

##### 配置信息
> config/autoload/xxl_job.php

```php
return [
    'enable' => env('XXL_JOB_ENABLE', true),
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8769/xxl-job-admin'),
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', null),
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    'executor_server' => [
        'prefix_url' => env('XXL_JOB_PREFIX_URL', 'php-xxl-job'),
    ],
];
```
#### BEAN模式(类形式)
Bean模式任务，支持基于类的开发方式，每个任务对应一个 PHP 类。

##### 步骤一：新建目录，开发 Job 类：
```php
class DemoJob extends AbstractJobHandler{}
```
##### 步骤二：调度中心，新建调度任务
```
1. 编写一个实现 Hyperf\XxlJob\Handler\JobHandlerInterface 的 Job 类，可直接继承 Hyperf\XxlJob\Handler\AbstractJobHandler 得到对应的实现
2. 注解配置：为 Job 类添加注解 "#[XxlJob('自定义JobHandler名称')]"，注解的 value 值对应的是调度中心新建任务的 JobHandler 属性的值
```
#### 完整示例

```php
namespace App\Job;

use Hyperf\Di\Annotation\Inject;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Handler\AbstractJobHandler;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;

#[XxlJob("demoJobClassHandler")]
class DemoJobClass extends AbstractJobHandler
{

    #[Inject]
    protected JobExecutorLoggerInterface $jobExecutorLogger;

    /**
     * 执行任务
     */
    public function execute(RunRequest $request): void
    {
        // 获取参数
        $params = $request->getExecutorParams();
        // 获取 LogId
        $logId = $request->getLogId();
        $this->jobExecutorLogger->log('demoJobClassHandler...');
        $this->jobExecutorLogger->log('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->jobExecutorLogger->log($i);
            $this->jobExecutorLogger->log('logId:' . $logId);
            $this->jobExecutorLogger->log('params:' . $params);
        }
    }
}
```

####  BEAN模式(方法形式)
基于方法的开发方式，每个任务对应一个方法，相对比类形式更加灵活，但也更难管理。

##### 步骤一：开发 Job 方法
对任意类中的 Public 方法增加 `#[XxlJob('自定义JobHandler名称')]` 注解，注解的 value 值对应的是调度中心新建任务的 JobHandler 属性的值

```php
use Hyperf\XxlJob\Annotation\XxlJob;

class Foo {

    #[XxlJob('demoJobHandler')]
    public function demoJobHandler(){}

}
```

##### 步骤二：调度中心，新建调度任务
对新建的任务进行参数配置，运行模式选中 `BEAN模式`，JobHandler 属性填写注解 “#[XxlJob]”中定义 value 值
![hMvJnQ](https://www.xuxueli.com/doc/static/xxl-job/images/img_ZAsz.png)

#### 完整示例

```php
namespace App\Job;

use Hyperf\Di\Annotation\Inject;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Logger\JobExecutorLoggerInterface;
use Hyperf\XxlJob\Requests\RunRequest;

class DemoJob
{
    
    #[Inject]
    protected JobExecutorLoggerInterface $jobExecutorLogger;
    
    /**
     * 1.任务示例.
     */
    #[XxlJob('demoJobHandler')]
    public function demoJobHandler(RunRequest $request)
    {
        //获取参数
        $params = $request->getExecutorParams();
        //获取logId
        $logId = $request->getLogId();
        $this->jobExecutorLogger->log('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->jobExecutorLogger->log($i);
            $this->jobExecutorLogger->log('logId:' . $logId);
            $this->jobExecutorLogger->log('params:' . $params);
        }
    }

    /**
     * 2、分片广播任务
     */
    #[XxlJob('shardingJobHandler')]
    public function shardingJobHandler(RunRequest $request)
    {
        // 分片参数
        $shardIndex = $request->getBroadcastIndex();
        $shardTotal = $request->getBroadcastTotal();
        $this->jobExecutorLogger->log(sprintf('分片参数：当前分片序号 = %d, 总分片数 = %d', $shardIndex, $shardTotal));
        // 业务逻辑
        for ($i = 0; $i < $shardTotal; ++$i) {
            if ($i == $shardIndex) {
                $this->jobExecutorLogger->log('第 %d 片, 命中分片开始处理', $i);
            } else {
                $this->jobExecutorLogger->log('第 %d 片, 忽略', $i);
            }
        }
    }

    /**
     * 3、执行命令.
     */
    #[XxlJob('commandJobHandler')]
    public function commandJobHandler(RunRequest $request)
    {
        //获取参数
        //例子:php -v
        $command = $request->getExecutorParams();
        var_dump($command);
        $result = System::exec($command);
        $message = str_replace("\n", '<br>', $result['output']);
        $this->jobExecutorLogger->log($message);
    }

    /**
     * 4、param任务
     *  参数示例：
     *      "url: http://www.baidu.com\n" +
     *      "method: get".
     */
    #[XxlJob('paramJobHandler')]
    public function paramJobHandler(RunRequest $request)
    {
        $param = $request->getExecutorParams();
        $array = explode(PHP_EOL, $param);
        /** array(2) {
              [0]=>
              string(25) "url: http://www.baidu.com"
              [1]=>
              string(11) "method: get"
            }
         */
        var_dump($param, $array);
    }

    /**
     * 5、任务示例：任务初始化与销毁时，支持自定义相关逻辑.
     */
    #[XxlJob(value: 'demoJob', init: 'initMethod', destroy: 'destroyMethod')]
    public function demoJob()
    {
        $this->jobExecutorLogger->log('demoJob run...');
    }

    public function initMethod()
    {
        $this->jobExecutorLogger->log('initMethod');
    }

    public function destroyMethod()
    {
        $this->jobExecutorLogger->log('destroyMethod');
    }
}
```