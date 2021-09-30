## PHP XxlJob Client

 xxlJob PHP 客户端

##### 优点

- 分布式任务调度平台
- 任务可以随时关闭与开启
- 日志可通过服务端查看


## 注意

> xxl-job 服务端版本 >= 2.2.0

> 不能取消正在执行的任务
## 安装

```
composer require hyperf/xxljob
```

## 使用

#### 发布配置文件

```bash
php bin/hyperf.php vendor:publish hyperf/xxljob
```
##### 配置信息
> config/autoload/xxl_job.php
```php
return [
    // enable false 将不会启动服务
    'enable' => true,
    //服务端地址
    'admin_address' => 'http://127.0.0.1:8769/xxl-job-admin',
    //执行器名称
    'app_name' => 'xxl-job-demo',
    //客户端请求前缀
    'prefix_url' => 'php-xxl-job',
    //access_token
    'access_token' => null,
    'log' => [
        'filename' => BASE_PATH . '/runtime/logs/xxl-job/job.log',
        //日志最大留存天数 0:不删除
        'maxDay' => 30,
    ],
];
```
#### BEAN模式(类形式)
Bean模式任务，支持基于类的开发方式，每个任务对应一个PHP类。
##### 步骤一：新建目录，开发Job类：
```php
class DemoJob extends AbstractJobHandler{}
```
##### 步骤二：调度中心，新建调度任务
```
1. 编写job类继承AbstractJobHandler
2. 注解配置：为Job类添加注解 "#[JobHandler('自定义jobhandler名称')]"，注解value值对应的是调度中心新建任务的JobHandler属性的值。
3. 执行日志：需要通过 "$this->getXxlJobHelper()->log('...')" 打印执行日志;
```
#### 完整示例
```php
namespace App\Job;

use Hyperf\XxlJob\Annotation\JobHandler;
use Hyperf\XxlJob\Handler\AbstractJobHandler;

#[JobHandler("demoJobClassHandler")]
class DemoJobClass extends AbstractJobHandler
{
    /**
     * 执行任务
     */
    public function execute(): void
    {
        //获取参数
        $params = $this->getXxlJobHelper()->getJobParam();
        //获取logId
        $logId = $this->getXxlJobHelper()->getRunRequest()->getLogId();
        $this->getXxlJobHelper()->log('demoJobClassHandler...');
        $this->getXxlJobHelper()->log('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->getXxlJobHelper()->log($i);
            $this->getXxlJobHelper()->log('logId:' . $logId);
            $this->getXxlJobHelper()->log('params:' . $params);
        }
    }
}
```
####  BEAN模式(方法形式)
基于方法的开发方式，每个任务对应一个方法。
##### 步骤一：开发Job方法
```php
#[XxlJob('demoJobHandler')]
public function demoJobHandler(){}
```
##### 步骤二：调度中心，新建调度任务
```
1. 编写job方法
2. 注解配置：在job方法添加注解 "#[XxlJob('自定义jobhandler名称')]"，注解value值对应的是调度中心新建任务的JobHandler属性的值。
3. 执行日志：注入XxlJobHelper类 通过 $this->xxlJobHelper->log('...') 打印执行日志;
```
对新建的任务进行参数配置，运行模式选中 “BEAN模式”，JobHandler属性填写任务注解“#[XxlJob]”中定义的值
![hMvJnQ](https://www.xuxueli.com/doc/static/xxl-job/images/img_ZAsz.png)

#### 完整示例
```php
namespace App\Job;

use Hyperf\Di\Annotation\Inject;
use Hyperf\XxlJob\Annotation\XxlJob;
use Hyperf\XxlJob\Logger\XxlJobHelper;
use Swoole\Coroutine\System;

class DemoJob
{
    #[Inject]
    private XxlJobHelper $xxlJobHelper;
    
    /**
     * 1.任务示例.
     */
    #[XxlJob('demoJobHandler')]
    public function demoJobHandler()
    {
        //获取参数
        $params = $this->xxlJobHelper->getJobParam();
        //获取logId
        $logId = $this->xxlJobHelper->getRunRequest()->getLogId();
        $this->xxlJobHelper->log('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->xxlJobHelper->log($i);
            $this->xxlJobHelper->log('logId:' . $logId);
            $this->xxlJobHelper->log('params:' . $params);
        }
    }

    /**
     * 2、分片广播任务
     */
    #[XxlJob('shardingJobHandler')]
    public function shardingJobHandler()
    {
        // 分片参数
        $shardIndex = $this->xxlJobHelper->getRunRequest()->getBroadcastIndex();
        $shardTotal = $this->xxlJobHelper->getRunRequest()->getBroadcastTotal();
        $this->xxlJobHelper->log(sprintf('分片参数：当前分片序号 = %d, 总分片数 = %d', $shardIndex, $shardTotal));
        // 业务逻辑
        for ($i = 0; $i < $shardTotal; ++$i) {
            if ($i == $shardIndex) {
                $this->xxlJobHelper->log('第 %d 片, 命中分片开始处理', $i);
            } else {
                $this->xxlJobHelper->log('第 %d 片, 忽略', $i);
            }
        }
    }

    /**
     * 3、执行命令.
     */
    #[XxlJob('commandJobHandler')]
    public function commandJobHandler()
    {
        //获取参数
        //例子:php -v
        $command = $this->xxlJobHelper->getJobParam();
        var_dump($command);
        $result = System::exec($command);
        $message = str_replace("\n", '<br>', $result['output']);
        $this->xxlJobHelper->log($message);
    }

    /**
     * 4、param任务
     *  参数示例：
     *      "url: http://www.baidu.com\n" +
     *      "method: get".
     */
    #[XxlJob('paramJobHandler')]
    public function paramJobHandler()
    {
        $param = $this->xxlJobHelper->getJobParam();
        $array = explode(PHP_EOL, $param);
        /*
         * array(2) {
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
        $this->xxlJobHelper->log('demoJob run...');
    }

    public function initMethod()
    {
        $this->xxlJobHelper->log('initMethod');
    }

    public function destroyMethod()
    {
        $this->xxlJobHelper->log('destroyMethod');
    }
}

```
详细文档 [xxl-job](https://www.xuxueli.com/xxl-job) 
