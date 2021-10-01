# xxl-job-incubator

此为 xxl-job 的 PHP 版本的任务执行器(Job Executor)，特别适配于 Hyperf 框架，其余框架尚未验证适配性

### 优点

- 分布式任务调度平台
- 任务可以随时关闭与开启
- 日志可通过服务端查看


## 使用须知

> 1. xxl-job 服务端版本需 >= 2.2.0   
> 2. 无法取消正在执行的任务

## 安装

```
composer require hyperf/xxl-job-incubator
```

## 使用

### 配置

配置文件: `config/autoload/xxl_job.php`

```php
return [
    // 是否启用
    'enable' => env('XXL_JOB_ENABLE', true),
    // XXL-JOB 服务端地址
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8769/xxl-job-admin'),
    // 对应的 AppName
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    // 访问凭证
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', ''),
    // 执行器心跳间隔（秒）
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    // 执行器 HTTP Server 相关配置
    'executor_server' => [
        // HTTP Server 路由前缀
        'prefix_url' => env('XXL_JOB_PREFIX_URL', 'php-xxl-job'),
    ],
];
```

如文件不存在可通过以下命令发布配置文件

```bash
php bin/hyperf.php vendor:publish hyperf/xxl-job-incubator
```

### Bean 模式(类形式)

Bean 模式任务，支持基于类的开发方式，每个任务对应一个 PHP 类

优点：与 Hyperf 整合性好，易于管理
缺点：任务运行于单独的，协程任务代码不能存在阻塞 IO，每个 Job 需占用一个类文件，Job 逻辑简单但数量过多时过于累赘

#### 编写 Job 类

编写一个实现 `Hyperf\XxlJob\Handler\JobHandlerInterface` 的 Job 类，并为 Job 类添加注解 `#[XxlJob('value')]`，注解的 value 值对应的是调度中心新建任务的 JobHandler 属性的值，如下所示：

> Tips: 可直接继承 `Hyperf\XxlJob\Handler\AbstractJobHandler` 得到对应的实现

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

#### 在调度中心新建调度任务

对新建的任务进行参数配置，运行模式选中 `BEAN模式`，JobHandler 属性填写注解 `#[XxlJob]`中定义 value 值
![hMvJnQ](https://www.xuxueli.com/doc/static/xxl-job/images/img_ZAsz.png)

### Bean 模式(方法形式)

基于方法的开发方式，每个任务对应一个方法

优点：相对比 `Bean(类形式)` 更加灵活
缺点：数量多时更难管理，代码复杂度高时多个任务间容易造成耦合度过高

#### 编写 Job 方法

对任意类中的 Public 方法增加 `#[XxlJob('value')]` 注解，注解的 value 值对应的是调度中心新建任务的 JobHandler 属性的值

```php
use Hyperf\XxlJob\Annotation\XxlJob;

class Foo {

    #[XxlJob('demoJobHandler')]
    public function demoJobHandler(){}

}
```

#### 在调度中心新建调度任务

对新建的任务进行参数配置，运行模式选中 `BEAN模式`，JobHandler 属性填写注解 `#[XxlJob]`中定义 value 值
![hMvJnQ](https://www.xuxueli.com/doc/static/xxl-job/images/img_ZAsz.png)

#### 使用案例

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

### Glue 脚本模式

该模式下，可支持任务以将源码方式维护在调度中心，支持通过 XXL-JOB 提供的 Web IDE 在线编写代码和在线更新，因此不需要指定固定的 `JobHandler`，脚本模式支持多种脚本语言编写 Job 代码，包括 PHP、Python、NodeJs、Shell、PowerShell，在 XXL-JOB 新建任务时选择对应的模式即可，例如 `GLUE(PHP)` 即代表 PHP 语言的脚本模式，所有脚本模式的任务会以一个独立的进程来运行，故在 PHP 下也可支持编写存在 IO 阻塞的代码

> 要使用 `Glue 脚本模式` 必须配置 Access Token 方可启用

优点：极度灵活，可以实现不重启新增和修改 Job 代码，支持多种脚本语言，独立进程
缺点：大批量任务时容易造成进程数过多，脚本代码由 XXL-JOB 远程编辑发放容易导致安全问题，Job 代码可对 Executor 所在服务器环境进行与启动 Hyperf 应用的权限相同的操作

### 引用

关于 XXL-JOB 更多的使用细节可参考 [XXL-JOB 官方文档](https://www.xuxueli.com/xxl-job/#%E3%80%8A%E5%88%86%E5%B8%83%E5%BC%8F%E4%BB%BB%E5%8A%A1%E8%B0%83%E5%BA%A6%E5%B9%B3%E5%8F%B0XXL-JOB%E3%80%8B)