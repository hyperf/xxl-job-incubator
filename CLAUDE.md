# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

这是 **XXL-JOB** 的 PHP 版本任务执行器（Job Executor），专为 Hyperf 3.1 框架设计。作为 Hyperf 的子包发布（`hyperf/xxl-job-incubator`），命名空间为 `Hyperf\XxlJob\`。

## 常用命令

```bash
# 运行测试
composer test

# 运行单个测试
vendor/bin/phpunit --filter=测试方法名

# 静态分析
composer analyse

# 代码格式化（src 和 tests）
composer cs-fix

# 发布配置文件到宿主项目
php bin/hyperf.php vendor:publish hyperf/xxl-job-incubator
```

## 核心架构

### 请求处理链路

```
XXL-JOB Admin 调度中心
  → HTTP 请求抵达 Executor（通过 BootAppRouteListener 注册的路由）
  → JobController（run/log/beat/idleBeat/kill）
  → JobService.executorBlockStrategy() 验证 handler 有效性
  → JobService.send() 发送任务
     ├─ Swoole 引擎：通过 PipeMessage 发送到 JobDispatcherProcess
     └─ 其他引擎：直接调用 JobSerialExecutionService
  → GlueHandlerManager.handle() 按 GlueType 分发
     ├─ BEAN 模式 → BeanHandler → 根据 executionMode 选择执行器
     │    ├─ COROUTINE → JobExecutorCoroutine（协程内执行）
     │    └─ PROCESS → JobExecutorProcess（启动独立子进程执行 php execute:xxl-job）
     └─ 脚本模式（Shell/Python/PHP/NodeJS/PowerShell）→ ScriptHandler
```

### 两种执行模式

| 模式 | 执行器 | 任务运行方式 | 终止支持 |
|------|--------|-------------|---------|
| **协程模式** (`coroutine`) | `JobExecutorCoroutine` | 在同一个进程的协程中执行 | 仅 Swoole ≥ 6.1 通过 `Coroutine::cancel()` 终止 |
| **进程模式** (`process`) | `JobExecutorProcess` | 启动独立子进程运行 `execute:xxl-job` 命令 | 通过 `kill -9` 强杀进程树，安全可靠 |

- Swoole 引擎默认使用进程模式；Swow 引擎默认使用协程模式
- 可通过注解 `#[XxlJob(executionMode: 'process|coroutine')]` 或全局配置 `execution_mode` 手动指定

### Bean 任务注册机制

`BootAppRouteListener` 在应用启动时扫描 `#[XxlJob]` 注解：

- **方法注解**：标记任意类的 public 方法为任务处理器
- **类注解**：标记实现 `JobHandlerInterface` 的类为任务处理器（执行 `execute` 方法）
- 扫描结果注册到 `JobHandlerManager`，以注解的 `value` 为 key
- 支持 `init`/`destroy` 回调方法

### 阻塞处理策略（BlockStrategy）

在 `JobSerialExecutionService` 中实现，使用协程 Channel 作为任务队列：

- **串行执行**（默认）：任务排队，按 FIFO 顺序执行
- **丢弃后续调度**（Discard Later）：已有任务运行时，拒绝新请求
- **覆盖之前调度**（Cover Early）：终止当前运行的任务，执行新任务

### Dispatcher 进程生命周期

`JobDispatcherProcess` 是接收 PipeMessage 的专用进程，可通过 `max_process_lifetime` 配置最大存活时间（秒），到期后会自动退出等待 Swoole 重启，实现定期回收。

### 事件系统

- `BeforeJobRun` — 任务执行前触发
- `AfterJobRun` — 任务执行后触发（在 `finally` 块中保证触发）
- 通过 `Psr\EventDispatcher\EventDispatcherInterface` 分发

### 日志系统

- `JobExecutorLoggerInterface` — 日志接口，写入任务专属日志文件
- `JobExecutorFileLogger` — 文件日志实现，日志按 logId 和日期存储
- `JobExecutorStdoutLogger` — 标准输出日志实现
- 日志文件存储在 `runtime/xxl_job/logs/` 目录
- 通过 `MainWorkerStartListener` 定期清理过期日志（`log_retention_days` 配置）

### 配置体系

- `Config` — 值对象，存储所有配置项（通过 getter/setter 访问）
- `ConfigFactory` — 实现 `Config::class`，从 Hyperf 配置中读取并构建 `Config` 实例
- 宿主项目的配置文件：`config/autoload/xxl_job.php`

### PipeMessage 机制（Swoole 专用）

在 Swoole 引擎下，HTTP 请求到达的 Worker 进程通过 `$process->exportSocket()->send()` 将 `JobPipeMessage`（包含 RunRequest 或 killJobId）发送给 `JobDispatcherProcess`，由 `OnPipeMessageListener` 接收后在协程中处理。这确保了任务执行在独立的 dispatcher 进程中。

## 关键类速查

| 类 | 职责 |
|---|---|
| `ConfigProvider` | Hyperf 组件入口：注册依赖、监听器、注解扫描、进程 |
| `BootAppRouteListener` | 启动时注册 HTTP 路由、扫描 #[XxlJob] 注解、配置执行器 URL |
| `MainWorkerStartListener` | 启动心跳注册和过期日志清理 |
| `JobController` | HTTP 端点：/run、/log、/beat、/idleBeat、/kill |
| `JobService` | 任务调度入口：验证 handler → 发送 PipeMessage 或直接调用执行 |
| `JobSerialExecutionService` | 阻塞策略实现：Channel 队列管理、Cover Early/Discard Later 逻辑 |
| `GlueHandlerManager` | 按 GlueType 分发到对应的 Handler（BeanHandler/ScriptHandler） |
| `BeanHandler` | BEAN 模式：查 `JobHandlerManager` 获取定义 → 选择执行器 → 调用 init/method/destroy |
| `AbstractJobExecutor` | 执行器基类：生命周期管理（BeforeJobRun → callback → AfterJobRun）、异常处理、回调 |
| `JobHandlerManager` | 存储所有已注册的 JobHandler 定义 |
| `ApiRequest` | 与 XXL-JOB Admin 的 HTTP 通信：注册、回调、多回调 |
| `JobContext` | 协程上下文：存储当前 logId、RunRequest（基于 Hyperf Context） |
| `JobRunContent` | 运行时任务追踪：记录正在运行的 jobId → 对应 RunRequest 映射 |

## 任务类型

1. **Bean 模式（类形式）**：继承 `AbstractJobHandler`，实现 `execute(RunRequest)` 方法，类上加 `#[XxlJob('jobName')]`
2. **Bean 模式（方法形式）**：在任意类的 public 方法上加 `#[XxlJob('jobName')]`，方法签名接收 `RunRequest`
3. **Glue 脚本模式**：代码在 XXL-JOB Admin 端编写，支持 PHP/Python/NodeJS/Shell/PowerShell，以独立进程运行