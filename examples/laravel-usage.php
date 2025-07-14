<?php

declare(strict_types=1);

/**
 * Laravel 使用示例.
 *
 * 本文件展示了如何在 Laravel 应用中使用 Mellivora Logger Factory
 */

// 1. 基本使用 - 辅助函数
echo "=== 辅助函数使用示例 ===\n";

// 记录到默认通道
mlog('info', 'Application started');
mlog('error', 'Database connection failed', ['host' => 'localhost']);

// 记录到指定通道
mlog_with('api', 'info', 'API request received');
mlog_with('security', 'warning', 'Suspicious login attempt');

// 便捷的级别函数
mlog_debug('Debug information');
mlog_info('User logged in', ['user_id' => 123]);
mlog_warning('High memory usage detected');
mlog_error('Payment processing failed');
mlog_critical('System is down');

// 异常记录
try {
    throw new RuntimeException('Something went wrong', 500);
} catch (Exception $e) {
    mlog_exception($e, 'error', 'application');
}

// 2. Facade 使用
echo "\n=== Facade 使用示例 ===\n";

use Mellivora\Logger\Laravel\Facades\MLog;

// 基本日志记录
MLog::log('info', 'Facade logging test');
MLog::logWith('api', 'debug', 'API debug message');

// 便捷方法
MLog::info('User action', ['action' => 'profile_update']);
MLog::error('System error', ['component' => 'payment']);

// 异常记录
try {
    throw new InvalidArgumentException('Invalid data provided');
} catch (Exception $e) {
    MLog::exception($e, 'warning', 'validation');
}

// 获取 Logger 实例
$apiLogger = MLog::channel('api');
$apiLogger->info('Direct logger usage');

// 3. 依赖注入使用
echo "\n=== 依赖注入使用示例 ===\n";

class UserService
{
    public function __construct(
        private Mellivora\Logger\LoggerFactory $loggerFactory,
    ) {
    }

    public function createUser(array $userData): array
    {
        $logger = $this->loggerFactory->get('user');

        $logger->info('User creation started', ['data' => $userData]);

        try {
            // 模拟用户创建逻辑
            $user = [
                'id' => mt_rand(1000, 9999),
                'name' => $userData['name'] ?? 'Unknown',
                'email' => $userData['email'] ?? 'unknown@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $logger->info('User created successfully', ['user_id' => $user['id']]);

            return $user;
        } catch (Exception $e) {
            $logger->error('User creation failed', [
                'error' => $e->getMessage(),
                'data' => $userData,
            ]);

            throw $e;
        }
    }
}

// 4. 控制器示例
echo "\n=== 控制器使用示例 ===\n";

class ApiController
{
    public function handleRequest(array $requestData): array
    {
        // 记录请求开始
        mlog_with('api', 'info', 'API request started', [
            'endpoint' => '/api/users',
            'method' => 'POST',
            'data' => $requestData,
        ]);

        try {
            // 验证请求数据
            if (empty($requestData['name'])) {
                throw new InvalidArgumentException('Name is required');
            }

            // 处理业务逻辑
            $result = [
                'success' => true,
                'data' => [
                    'id' => mt_rand(1, 1000),
                    'name' => $requestData['name'],
                    'processed_at' => date('Y-m-d H:i:s'),
                ],
            ];

            // 记录成功
            mlog_with('api', 'info', 'API request processed successfully', [
                'result' => $result,
            ]);

            return $result;
        } catch (InvalidArgumentException $e) {
            // 记录验证错误
            mlog_with('api', 'warning', 'API validation failed', [
                'error' => $e->getMessage(),
                'data' => $requestData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            // 记录系统错误
            mellivora_exception($e, 'error', 'api');

            return [
                'success' => false,
                'error' => 'Internal server error',
            ];
        }
    }
}

// 5. 中间件示例
echo "\n=== 中间件使用示例 ===\n";

class RequestLoggingMiddleware
{
    public function handle(array $request, callable $next): array
    {
        $startTime = microtime(true);

        // 记录请求开始
        mlog_with('request', 'info', 'Request started', [
            'url' => $request['url'] ?? '/unknown',
            'method' => $request['method'] ?? 'GET',
            'ip' => $request['ip'] ?? '127.0.0.1',
        ]);

        try {
            // 处理请求
            $response = $next($request);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // 记录请求完成
            mlog_with('request', 'info', 'Request completed', [
                'url' => $request['url'] ?? '/unknown',
                'status' => $response['status'] ?? 200,
                'duration_ms' => $duration,
            ]);

            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // 记录请求失败
            mlog_with('request', 'error', 'Request failed', [
                'url' => $request['url'] ?? '/unknown',
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }
}

// 6. 队列任务示例
echo "\n=== 队列任务使用示例 ===\n";

class ProcessEmailJob
{
    public function handle(array $emailData): void
    {
        mlog_with('queue', 'info', 'Email job started', [
            'job' => 'ProcessEmailJob',
            'email' => $emailData['to'] ?? 'unknown',
        ]);

        try {
            // 模拟邮件发送
            sleep(1); // 模拟处理时间

            if (mt_rand(1, 10) > 8) {
                throw new Exception('Email service unavailable');
            }

            mlog_with('queue', 'info', 'Email sent successfully', [
                'job' => 'ProcessEmailJob',
                'email' => $emailData['to'] ?? 'unknown',
            ]);
        } catch (Exception $e) {
            mlog_with('queue', 'error', 'Email job failed', [
                'job' => 'ProcessEmailJob',
                'email' => $emailData['to'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

// 7. 运行示例
echo "\n=== 运行示例 ===\n";

// 创建服务实例（在实际 Laravel 应用中会通过容器自动注入）
$loggerFactory = new Mellivora\Logger\LoggerFactory([
    'default' => 'app',
    'formatters' => [
        'line' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'params' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            ],
        ],
    ],
    'handlers' => [
        'console' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'params' => ['php://stdout', Monolog\Level::Debug],
            'formatter' => 'line',
        ],
    ],
    'loggers' => [
        'app' => ['console'],
        'api' => ['console'],
        'user' => ['console'],
        'request' => ['console'],
        'queue' => ['console'],
    ],
]);

// 模拟 Laravel 容器绑定
class MockApp
{
    private static array $bindings = [];

    public static function bind(string $abstract, mixed $concrete): void
    {
        self::$bindings[$abstract] = $concrete;
    }

    public static function make(string $abstract): mixed
    {
        return self::$bindings[$abstract] ?? null;
    }
}

MockApp::bind(Mellivora\Logger\LoggerFactory::class, $loggerFactory);

// 运行示例
$userService = new UserService($loggerFactory);
$user = $userService->createUser(['name' => 'John Doe', 'email' => 'john@example.com']);
echo 'Created user: ' . json_encode($user) . "\n";

$controller = new ApiController();
$result = $controller->handleRequest(['name' => 'Jane Doe']);
echo 'API result: ' . json_encode($result) . "\n";

$middleware = new RequestLoggingMiddleware();
$request = ['url' => '/api/test', 'method' => 'GET', 'ip' => '192.168.1.1'];
$response = $middleware->handle($request, function ($req) {
    return ['status' => 200, 'data' => 'Success'];
});
echo 'Middleware result: ' . json_encode($response) . "\n";

$emailJob = new ProcessEmailJob();
try {
    $emailJob->handle(['to' => 'user@example.com', 'subject' => 'Welcome']);
    echo "Email job completed successfully\n";
} catch (Exception $e) {
    echo 'Email job failed: ' . $e->getMessage() . "\n";
}

echo "\n=== 示例完成 ===\n";
