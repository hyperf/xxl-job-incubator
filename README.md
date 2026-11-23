# xxl-job-incubator

此为 xxl-job 的 PHP 版本的任务执行器(Job Executor)，特别适配于 Hyperf 框架，其余框架尚未验证适配性

### 优点

- 分布式任务调度平台
- 任务可以随时关闭与开启
- 日志可通过服务端查看


## 使用须知

> 1. xxl-job 服务端版本需 >= 3.5.0（执行器组维度 AccessToken）；2.2.0 ~ 3.4.x 请使用本包 0.10.* 版本
> 2. 阻塞处理策略:已全部适配
> 3. 建议优先使用进程模式执行任务，终止任务完善且安全 
> 4. swoole版本不小于6.1版本，协程模式执行任务时，终止任务通过`cancel`方法停止

## 安装

```
composer require hyperf/xxl-job-incubator
```

> 本版本仅适配 xxl-job >= 3.5.0；对接 xxl-job 2.2.0 ~ 3.4.x 请安装 `0.10.*` 版本。

## 使用

### 配置

配置文件: `config/autoload/xxl_job.php`

```php
use function Hyperf\Support\env;

return [
    // 是否启动 xxl-job
    'enable' => env('XXL_JOB_ENABLE', true),
    // xxl-job admin 地址
    'admin_address' => env('XXL_JOB_ADMIN_ADDRESS', 'http://127.0.0.1:8080/xxl-job-admin'),
    // xxl-job app_name
    'app_name' => env('XXL_JOB_APP_NAME', 'xxl-job-demo'),
    // xxl-job access_token（3.5.0 起需与调度中心「执行器管理」中该 app_name 的 AccessToken 一致）
    'access_token' => env('XXL_JOB_ACCESS_TOKEN', ''),
    // 是否允许 GLUE（Shell/Python/PHP/NodeJS/PowerShell）任务，关闭后仅允许 BEAN
    'glue_enabled' => env('XXL_JOB_GLUE_ENABLED', true),
    // xxl-job 心跳时间
    'heartbeat' => env('XXL_JOB_HEARTBEAT', 30),
    // xxl-job 日志保留天数
    'log_retention_days' => 30,
    // xxl-job 执行器配置
    'executor_server' => [
        // executor host (no Settings, automatically obtained)
        'host' => env('XXL_JOB_EXECUTOR_HOST'),
        // executor port
        'port' => env('XXL_JOB_EXECUTOR_PORT'),
        // executor prefix
        'prefix_url' => env('XXL_JOB_EXECUTOR_PREFIX_URL', 'php-xxl-job')
    ],
    'guzzle_config' => [
        'headers' => [
            'charset' => 'UTF-8',
        ],
        'timeout' => 10,
    ],
    'file_logger' => [
        'dir' => BASE_PATH . '/runtime/xxl_job/logs/',
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
> swoole默认使用命令行模式执行，swow默认使用协程模式执行
> 如需要手动指定模式，通过注解XxlJob指定运行模式
> swoole使用协程模式，无法使用:`关闭终止任务`|`以及阻塞处理策略`|`任务超时`

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
        $this->jobExecutorLogger->info('demoJobClassHandler...');
        $this->jobExecutorLogger->info('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->jobExecutorLogger->info($i);
            $this->jobExecutorLogger->info('logId:' . $logId);
            $this->jobExecutorLogger->info('params:' . $params);
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
        $this->jobExecutorLogger->info('params:' . $params);
        for ($i = 1; $i < 5; ++$i) {
            sleep(2);
            $this->jobExecutorLogger->info($i);
            $this->jobExecutorLogger->info('logId:' . $logId);
            $this->jobExecutorLogger->info('params:' . $params);
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
        $this->jobExecutorLogger->info(sprintf('分片参数：当前分片序号 = %d, 总分片数 = %d', $shardIndex, $shardTotal));
        // 业务逻辑
        for ($i = 0; $i < $shardTotal; ++$i) {
            if ($i == $shardIndex) {
                $this->jobExecutorLogger->info('第 %d 片, 命中分片开始处理', $i);
            } else {
                $this->jobExecutorLogger->info('第 %d 片, 忽略', $i);
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
        $this->jobExecutorLogger->info($message);
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
        $this->jobExecutorLogger->info('demoJob run...');
    }

    public function initMethod()
    {
        $this->jobExecutorLogger->info('initMethod');
    }

    public function destroyMethod()
    {
        $this->jobExecutorLogger->info('destroyMethod');
    }
}
```

### 命令行直接调用

支持不依赖 XXL-JOB Admin 调度中心，直接在命令行调用已注册的 Job Handler，方便开发调试和手动触发任务。

```bash
# 直接调用已注册的 Handler
php bin/hyperf.php execute:xxl-job --handler=demoJobHandler

# 带参数调用
php bin/hyperf.php execute:xxl-job --handler=demoJobHandler --params='{"key":"value"}'

# 带参数字符串调用
php bin/hyperf.php execute:xxl-job --handler=paramJobHandler --params='url: http://www.baidu.com\nmethod: get'
```

| 参数 | 说明 |
|------|------|
| `--handler` | (必填) 任务 Handler 名称，对应 `#[XxlJob]` 注解中的 value 值 |
| `--params` | (可选) 任务参数，传入后可通过 `RunRequest::getExecutorParams()` 获取 |

> 该方式通过构造一个模拟的 `RunRequest` 直接执行任务，`jobId` 和 `logId` 均为 0，不会向 XXL-JOB Admin 回调执行结果。

### 按调度记录执行（进程模式内部命令）

进程模式的父进程会通过以下命令拉起子进程，`jobId` 与 `logId` 必须同时提供，并且与当前运行信息文件中的记录一致：

```bash
php bin/hyperf.php execute:xxl-job --jobId=100 --logId=10001
```

- `--jobId`：XXL-JOB Admin 的任务 ID。
- `--logId`：本次调度日志 ID，必须与该任务当前运行信息中的 `logId` 相同，防止旧子进程误执行新一代请求。
- 只提供 `--jobId`、缺少 `--logId`，或二者与运行信息不匹配时，命令会抛出异常并返回非零状态。
- 该模式主要用于执行器内部调度；手工调试请优先使用上文的 `--handler` 方式，不依赖运行信息文件。

### 任务管理 API（调度中心 3.5.0 OpenAPI）

xxl-job 3.5.0 起，调度中心提供了一组任务管理 OpenAPI（`/api/addJob`、`/api/updateJob`、`/api/removeJob`、`/api/startJob`、`/api/stopJob`、`/api/triggerJob`），用于三方集成系统管理任务生命周期。**官方执行器本身不调用这组接口**；本组件将其封装为 `JobAdminService` 与 `xxl-job:job` 命令，供业务系统/运维脚本使用。

> 仅支持 xxl-job >= 3.5.0；旧版调度中心请使用本包 `0.10.*`。

鉴权复用执行器配置：`admin_address`、`app_name`、`access_token`（`ApiRequest` 自动注入 `XXL-JOB-ACCESS-TOKEN`、`XXL-JOB-APPNAME`）。`jobGroup` 是执行器组主键（`xxl_job_group.id`），需从调度中心「执行器管理」或数据库获取，与请求头 `app_name` 不是同一个概念。

#### PHP 调用

```php
use Hyperf\Di\Annotation\Inject;
use Hyperf\XxlJob\Enum\ExecutorBlockStrategyEnum;
use Hyperf\XxlJob\Glue\GlueEnum;
use Hyperf\XxlJob\Service\JobAdminService;

#[Inject]
protected JobAdminService $jobAdminService;

// 新增任务，返回新任务 ID
$jobId = $this->jobAdminService->addJob([
    'jobGroup' => 1,
    'name' => 'demo job',
    'author' => 'admin',
    'scheduleType' => 'CRON',
    'scheduleConf' => '0 0/1 * * * ?',
    'misfireStrategy' => 'DO_NOTHING',
    'executorRouteStrategy' => 'FIRST',
    'executorHandler' => 'demoJobHandler',
    'executorBlockStrategy' => ExecutorBlockStrategyEnum::SERIAL_EXECUTION,
    'executorTimeout' => 0,
    'executorFailRetryCount' => 0,
    'glueType' => GlueEnum::BEAN,
]);

$this->jobAdminService->updateJob(['id' => $jobId /*, 其余完整字段 */]);
$this->jobAdminService->startJob($jobId);
$this->jobAdminService->triggerJob($jobId, '{"k":"v"}');
$this->jobAdminService->stopJob($jobId);
$this->jobAdminService->removeJob($jobId);
```

#### 命令行

```bash
# 新增（内联 JSON 或文件）
php bin/hyperf.php xxl-job:job add --json='{"jobGroup":1,"name":"demo","author":"admin","scheduleType":"CRON","scheduleConf":"0 0/1 * * * ?","executorRouteStrategy":"FIRST","executorHandler":"demoJobHandler","executorBlockStrategy":"SERIAL_EXECUTION","glueType":"BEAN"}'
php bin/hyperf.php xxl-job:job add --file=/tmp/job.json

# 更新（--id 会覆盖 payload 中的 id）
php bin/hyperf.php xxl-job:job update --id=12 --file=/tmp/job.json

# 启动 / 停止 / 手动触发 / 删除
php bin/hyperf.php xxl-job:job start --id=12
php bin/hyperf.php xxl-job:job stop --id=12
php bin/hyperf.php xxl-job:job trigger --id=12 --executor-param='{"k":"v"}'
php bin/hyperf.php xxl-job:job remove --id=12
```

#### 注意事项

- 响应统一 `{code,msg,data}`；admin 业务失败也返回 HTTP 200，客户端会把 `code != 200` 转成 `XxlJobException`；`code=502` 表示触发限流（100 次/30 秒）。
- `executorHandler`：admin 会无条件 `trim()`，GLUE 任务也必须传空串 `''`；BEAN 任务须与 `#[XxlJob]` 的值一致。
- `updateJob` 是**整体覆盖**而非 patch：`name`/`author` 必填，未传字段会被置空/0；不支持修改 `jobGroup`；3.5.0 不更新 `glueType/glueSource/glueRemark`，且会清空 `childJobId`。
- `scheduleConf`：`NONE` 传空串、`CRON` 传合法 cron、`FIX_RATE` 传 >= 1 的秒数。
- 可选值：`scheduleType` = NONE/CRON/FIX_RATE；`misfireStrategy` = DO_NOTHING/FIRE_ONCE_NOW；`executorRouteStrategy` = FIRST/LAST/ROUND/RANDOM/CONSISTENT_HASH/LEAST_FREQUENTLY_USED/LEAST_RECENTLY_USED/FAILOVER/BUSYOVER/SHARDING_BROADCAST；`executorBlockStrategy` = SERIAL_EXECUTION/DISCARD_LATER/COVER_EARLY；`glueType` = BEAN/GLUE_GROOVY/GLUE_SHELL/GLUE_PYTHON/GLUE_NODEJS/GLUE_POWERSHELL/GLUE_PHP。
- `triggerJob` 只提交一次调度（异步执行）；`addressList` 为空时由调度中心从注册中心取地址。
- OpenAPI 请求字段是 `executorParam`（单数），执行器触发体里是 `executorParams`（复数），注意区分。
- `addJob` 成功返回的 ID 在响应的 `data` 字段（官方文档写成 `content`，以源码为准）。
- `removeJob` 对不存在的任务 ID 也返回成功。

### Glue 脚本模式

该模式下，可支持任务以将源码方式维护在调度中心，支持通过 XXL-JOB 提供的 Web IDE 在线编写代码和在线更新，因此不需要指定固定的 `JobHandler`   
脚本模式支持多种脚本语言编写 Job 代码，包括 PHP、Python、NodeJs、Shell、PowerShell，在 XXL-JOB 新建任务时选择对应的模式即可，例如 `GLUE(PHP)` 即代表 PHP 语言的脚本模式，所有脚本模式的任务会以一个独立的进程来运行，故在 PHP 下也可支持编写存在 IO 阻塞的代码

> 要使用 `Glue 脚本模式` 必须配置 Access Token 且 `glue_enabled` 为 true（默认）；设为 false 时仅允许 BEAN 任务

优点：极度灵活，可以实现不重启新增和修改 Job 代码，支持多种脚本语言，独立进程   
缺点：大批量任务时容易造成进程数过多，脚本代码由 XXL-JOB 远程编辑发放容易导致安全问题，Job 代码可对 Executor 所在服务器环境进行与启动 Hyperf 应用的权限相同的操作


### 安装xxl-job-admin(3.5.0版本)
#### 初始化“调度数据库”
[初始化SQL脚本](https://github.com/xuxueli/xxl-job/blob/3.5.0/doc/db/tables_xxl_job.sql)
#### docker安装
```bash
docker run -d \
-e PARAMS="--spring.datasource.url=jdbc:mysql://127.0.0.1:3306/xxl_job?Unicode=true&characterEncoding=UTF-8&autoReconnect=true&serverTimezone=Asia/Shanghai
--spring.datasource.username=root
--spring.datasource.password=123456" \
-p 8080:8080 --name xxl-job --restart=always xuxueli/xxl-job-admin:3.5.0
```
> 替换:数据库地址/账号/密码

#### 升级到 3.5.0 注意事项

3.5.0 起 AccessToken 由全局配置改为「执行器组」维度，本包已按新协议适配，**仅支持 3.5.0 及以上调度中心**；对接 3.4.x 及更早版本请使用本包 `0.10.*`。

1. 执行官方升级脚本：`xxl_job_group` 增加 `access_token` 列、`app_name` 唯一索引，`title`/`job_desc` 字段改名，日志表新增 `alarm_status` 索引（详见 [官方 Release Notes](https://github.com/xuxueli/xxl-job/releases/tag/3.5.0)）。
2. 升级前检查重复 app_name：`SELECT app_name, COUNT(*) FROM xxl_job_group GROUP BY app_name HAVING COUNT(*) > 1;`，有重复需先清理，否则唯一索引创建失败。
3. 在调度中心「执行器管理」中为每个执行器组设置 AccessToken（规则 `^[a-z][a-z0-9_]*$`，长度 4~50），并确保与执行器配置 `access_token` 完全一致；`access_token` 为 NULL 的组会被拒绝注册/回调。
4. 执行器配置 `app_name` 必须与执行器组 AppName 完全一致（区分大小写）。
5. 已发布过配置文件的宿主项目需重新执行 `vendor:publish` 或手工补上 `glue_enabled`，否则仅改 `.env` 不会生效。

### 引用

关于 XXL-JOB 更多的使用细节可参考 [XXL-JOB 官方文档](https://www.xuxueli.com/xxl-job/#%E3%80%8A%E5%88%86%E5%B8%83%E5%BC%8F%E4%BB%BB%E5%8A%A1%E8%B0%83%E5%BA%A6%E5%B9%B3%E5%8F%B0XXL-JOB%E3%80%8B)