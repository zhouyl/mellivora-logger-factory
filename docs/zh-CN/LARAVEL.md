# Laravel 集成指南

本文档详细介绍如何在 Laravel 项目中集成和使用 Mellivora Logger Factory。

## 📋 系统要求

- **Laravel**: 10.x | 11.x
- **PHP**: 8.3+
- **Monolog**: ^3.0

## 🚀 安装

### 1. 安装包

```bash
composer require mellivora/logger-factory
```

### 2. 自动发现

Laravel 会自动发现并注册服务提供者，无需手动配置。

### 3. 发布配置文件

```bash
php artisan vendor:publish --tag=mellivora-logger-config
```

这将在 `config/mellivora-logger.php` 创建配置文件。

## ⚙️ 配置

### 基础配置

编辑 `config/mellivora-logger.php`：

```php
<?php

use Monolog\Level;

return [
    'default' => 'app',

    'formatters' => [
        'json' => [
            'class' => \Monolog\Formatter\JsonFormatter::class,
            'params' => [],
        ],
        'line' => [
            'class' => \Monolog\Formatter\LineFormatter::class,
            'params' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],
    ],

    'processors' => [
        'web' => [
            'class' => \Mellivora\Logger\Processor\WebProcessor::class,
            'params' => [
                'level' => Level::Debug,
            ],
        ],
        'memory' => [
            'class' => \Mellivora\Logger\Processor\MemoryProcessor::class,
            'params' => [
                'level' => Level::Debug,
                'realUsage' => true,
                'useFormatting' => true,
            ],
        ],
    ],

    'handlers' => [
        'daily' => [
            'class' => \Mellivora\Logger\Handler\NamedRotatingFileHandler::class,
            'params' => [
                'filename' => storage_path('logs/%channel%.log'),
                'maxBytes' => 10 * 1024 * 1024, // 10MB
                'backupCount' => 5,
                'level' => Level::Debug,
            ],
            'formatter' => 'line',
            'processors' => ['web', 'memory'],
        ],
        'error_mail' => [
            'class' => \Mellivora\Logger\Handler\SmtpHandler::class,
            'params' => [
                'mailer' => null, // 使用 Laravel 默认邮件配置
                'message' => [
                    'to' => env('LOG_ERROR_EMAIL', 'admin@example.com'),
                    'subject' => 'Application Error',
                ],
                'level' => Level::Error,
                'maxRecords' => 5,
            ],
            'formatter' => 'json',
        ],
    ],

    'loggers' => [
        'app' => ['daily'],
        'api' => ['daily'],
        'auth' => ['daily'],
        'error' => ['daily', 'error_mail'],
    ],
];
```

### 环境配置

在 `.env` 文件中添加相关配置：

```env
# 日志配置
LOG_ERROR_EMAIL=admin@example.com
LOG_LEVEL=debug

# 邮件配置（用于错误通知）
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 📚 使用方法

### 1. 依赖注入

```php
<?php

namespace App\Http\Controllers;

use Mellivora\Logger\LoggerFactory;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private LoggerFactory $loggerFactory
    ) {}

    public function index(Request $request)
    {
        $logger = $this->loggerFactory->get('api');
        $logger->info('User list requested', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        // 业务逻辑...
    }
}
```

### 2. Facade 使用

```php
<?php

use Mellivora\Logger\Facades\MLog;

// 使用默认通道
MLog::info('Application started');

// 使用指定通道
MLog::logWith('api', 'debug', 'API request', [
    'endpoint' => '/api/users',
    'method' => 'GET',
]);

// 记录异常
try {
    // 业务逻辑
} catch (\Exception $e) {
    MLog::exception($e, 'error', 'error');
}
```

### 3. 辅助函数

```php
<?php

// 快速日志记录
mlog('info', 'User logged in', ['user_id' => 123]);

// 指定通道
mlog_with('auth', 'info', 'Login attempt', [
    'username' => $username,
    'success' => true,
]);
```

### 4. 服务容器

```php
<?php

// 在服务提供者中
$logger = app('mellivora.logger.factory')->get('app');

// 或者
$logger = resolve(LoggerFactory::class)->get('api');
```

## 🔧 高级配置

### 中间件集成

创建日志中间件：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mellivora\Logger\LoggerFactory;

class RequestLogging
{
    public function __construct(
        private LoggerFactory $loggerFactory
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $logger = $this->loggerFactory->get('api');

        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        $logger->info('HTTP Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'user_id' => $request->user()?->id,
        ]);

        return $response;
    }
}
```

注册中间件：

```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\RequestLogging::class,
];
```

### 异常处理集成

在 `app/Exceptions/Handler.php` 中：

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Mellivora\Logger\LoggerFactory;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $loggerFactory = app(LoggerFactory::class);
            $logger = $loggerFactory->get('error');

            $logger->addException($e, \Monolog\Level::Error);
        });
    }
}
```

### 队列任务日志

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mellivora\Logger\LoggerFactory;

class ProcessDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(LoggerFactory $loggerFactory): void
    {
        $logger = $loggerFactory->get('queue');

        $logger->info('Job started', [
            'job_id' => $this->job->getJobId(),
            'queue' => $this->job->getQueue(),
        ]);

        try {
            // 处理逻辑
            $logger->info('Job completed successfully');
        } catch (\Exception $e) {
            $logger->addException($e);
            throw $e;
        }
    }
}
```

## 🎯 最佳实践

### 1. 通道分离

```php
// 按功能模块分离日志通道
'loggers' => [
    'app' => ['daily'],           // 应用主日志
    'api' => ['daily'],           // API 请求日志
    'auth' => ['daily'],          // 认证相关日志
    'payment' => ['daily'],       // 支付相关日志
    'error' => ['daily', 'mail'], // 错误日志（同时发送邮件）
    'audit' => ['daily'],         // 审计日志
    'performance' => ['daily'],   // 性能监控日志
],
```

### 2. 环境差异化配置

```php
// config/mellivora-logger.php
$handlers = [
    'daily' => [
        'class' => \Mellivora\Logger\Handler\NamedRotatingFileHandler::class,
        'params' => [
            'filename' => storage_path('logs/%channel%.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];

// 生产环境添加邮件通知
if (app()->environment('production')) {
    $handlers['error_mail'] = [
        'class' => \Mellivora\Logger\Handler\SmtpHandler::class,
        'params' => [
            'message' => [
                'to' => env('LOG_ERROR_EMAIL'),
                'subject' => 'Production Error Alert',
            ],
            'level' => \Monolog\Level::Error,
        ],
    ];
}

return [
    'handlers' => $handlers,
    // ...
];
```

### 3. 性能优化

```php
// 使用缓冲减少 I/O
'handlers' => [
    'buffered_daily' => [
        'class' => \Mellivora\Logger\Handler\NamedRotatingFileHandler::class,
        'params' => [
            'filename' => storage_path('logs/%channel%.log'),
            'bufferSize' => 100, // 缓冲 100 条记录
        ],
    ],
],
```

## 🧪 测试

### 单元测试

```php
<?php

namespace Tests\Feature;

use Mellivora\Logger\LoggerFactory;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    public function test_logger_factory_is_available()
    {
        $factory = app(LoggerFactory::class);
        $this->assertInstanceOf(LoggerFactory::class, $factory);
    }

    public function test_can_log_to_different_channels()
    {
        $factory = app(LoggerFactory::class);

        $appLogger = $factory->get('app');
        $apiLogger = $factory->get('api');

        $this->assertNotSame($appLogger, $apiLogger);
    }
}
```

### 功能测试

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mellivora\Logger\Facades\MLog;
use Tests\TestCase;

class ApiLoggingTest extends TestCase
{
    public function test_api_requests_are_logged()
    {
        // 模拟 API 请求
        $response = $this->getJson('/api/users');

        $response->assertStatus(200);

        // 验证日志记录
        // 注意：在测试环境中，可能需要使用 TestHandler 来验证日志
    }
}
```

## 🔧 故障排除

### 常见问题

1. **权限问题**
   ```bash
   sudo chown -R www-data:www-data storage/logs
   sudo chmod -R 755 storage/logs
   ```

2. **配置缓存**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **邮件发送失败**
   - 检查 `.env` 中的邮件配置
   - 确认 SMTP 服务器连接正常
   - 验证邮件地址格式

## 📞 支持

- **Laravel 文档**: [Laravel Logging](https://laravel.com/docs/logging)
- **问题反馈**: [GitHub Issues](https://github.com/zhouyl/mellivora-logger-factory/issues)
- **讨论**: [GitHub Discussions](https://github.com/zhouyl/mellivora-logger-factory/discussions)
