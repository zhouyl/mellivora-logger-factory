<?php

declare(strict_types=1);

/**
 * Laravel Usage Examples.
 *
 * This file demonstrates how to use Mellivora Logger Factory in Laravel applications
 */

// 1. Basic Usage - Helper Functions
echo "=== Helper Functions Usage Examples ===\n";

// Log to default channel
mlog('info', 'Application started');
mlog('error', 'Database connection failed', ['host' => 'localhost']);

// Log to specific channel
mlog_with('api', 'info', 'API request received');
mlog_with('security', 'warning', 'Suspicious login attempt');

// Convenient level functions
mlog_debug('Debug information');
mlog_info('User logged in', ['user_id' => 123]);
mlog_warning('High memory usage detected');
mlog_error('Payment processing failed');
mlog_critical('System is down');

// Exception logging
try {
    throw new RuntimeException('Something went wrong', 500);
} catch (Exception $e) {
    mlog_exception($e, 'error', 'application');
}

// 2. Facade Usage
echo "\n=== Facade Usage Examples ===\n";

use Mellivora\Logger\Laravel\Facades\MLog;

// Basic logging
MLog::log('info', 'Facade logging test');
MLog::logWith('api', 'debug', 'API debug message');

// Convenient methods
MLog::info('User action', ['action' => 'profile_update']);
MLog::error('System error', ['component' => 'payment']);

// Exception logging
try {
    throw new InvalidArgumentException('Invalid data provided');
} catch (Exception $e) {
    MLog::exception($e, 'warning', 'validation');
}

// Get Logger instance
$apiLogger = MLog::channel('api');
$apiLogger->info('Direct logger usage');

// 3. Dependency Injection Usage
echo "\n=== Dependency Injection Usage Examples ===\n";

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
            // Simulate user creation logic
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

// 4. Controller Example
echo "\n=== Controller Usage Examples ===\n";

class ApiController
{
    public function handleRequest(array $requestData): array
    {
        // Log request start
        mlog_with('api', 'info', 'API request started', [
            'endpoint' => '/api/users',
            'method' => 'POST',
            'data' => $requestData,
        ]);

        try {
            // Validate request data
            if (empty($requestData['name'])) {
                throw new InvalidArgumentException('Name is required');
            }

            // Process business logic
            $result = [
                'success' => true,
                'data' => [
                    'id' => mt_rand(1, 1000),
                    'name' => $requestData['name'],
                    'processed_at' => date('Y-m-d H:i:s'),
                ],
            ];

            // Log success
            mlog_with('api', 'info', 'API request processed successfully', [
                'result' => $result,
            ]);

            return $result;
        } catch (InvalidArgumentException $e) {
            // Log validation error
            mlog_with('api', 'warning', 'API validation failed', [
                'error' => $e->getMessage(),
                'data' => $requestData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            // Log system error
            mellivora_exception($e, 'error', 'api');

            return [
                'success' => false,
                'error' => 'Internal server error',
            ];
        }
    }
}

// 5. Middleware Example
echo "\n=== Middleware Usage Examples ===\n";

class RequestLoggingMiddleware
{
    public function handle(array $request, callable $next): array
    {
        $startTime = microtime(true);

        // Log request start
        mlog_with('request', 'info', 'Request started', [
            'url' => $request['url'] ?? '/unknown',
            'method' => $request['method'] ?? 'GET',
            'ip' => $request['ip'] ?? '127.0.0.1',
        ]);

        try {
            // Process request
            $response = $next($request);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Log request completion
            mlog_with('request', 'info', 'Request completed', [
                'url' => $request['url'] ?? '/unknown',
                'status' => $response['status'] ?? 200,
                'duration_ms' => $duration,
            ]);

            return $response;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Log request failure
            mlog_with('request', 'error', 'Request failed', [
                'url' => $request['url'] ?? '/unknown',
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }
}

// 6. Queue Job Example
echo "\n=== Queue Job Usage Examples ===\n";

class ProcessEmailJob
{
    public function handle(array $emailData): void
    {
        mlog_with('queue', 'info', 'Email job started', [
            'job' => 'ProcessEmailJob',
            'email' => $emailData['to'] ?? 'unknown',
        ]);

        try {
            // Simulate email sending
            sleep(1); // Simulate processing time

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

// 7. Running Examples
echo "\n=== Running Examples ===\n";

// Create service instance (in actual Laravel app, this would be auto-injected via container)
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

// Simulate Laravel container binding
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

// Run examples
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

echo "\n=== Examples Completed ===\n";
