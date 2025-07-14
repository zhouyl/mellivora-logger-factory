# Laravel Integration Guide

This document provides detailed instructions on how to integrate and use Mellivora Logger Factory in Laravel projects.

## 📋 System Requirements

- **Laravel**: 10.x | 11.x
- **PHP**: 8.3+
- **Monolog**: ^3.0

## 🚀 Installation

### 1. Install Package

```bash
composer require mellivora/logger-factory:^2.0.0-alpha
```

### 2. Auto-Discovery

Laravel will automatically discover and register the service provider, no manual configuration required.

### 3. Publish Configuration File

```bash
php artisan vendor:publish --tag=mellivora-logger-config
```

This will create a configuration file at `config/mellivora-logger.php`.

## ⚙️ Configuration

### Basic Configuration

Edit `config/mellivora-logger.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | Default log channel name. When calling mlog() function without 
    | specifying a channel, this channel will be used.
    |
    */
    'default_channel' => env('MELLIVORA_LOG_CHANNEL', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Configure different log channels for different purposes.
    | Each channel can have multiple handlers.
    |
    */
    'channels' => [
        'default' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/mellivora.log'),
                    'level' => env('MELLIVORA_LOG_LEVEL', 'debug'),
                    'max_files' => 30,
                ],
            ],
        ],

        'api' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/api.log'),
                    'level' => 'info',
                    'max_files' => 30,
                ],
            ],
        ],

        'queue' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/queue.log'),
                    'level' => 'info',
                    'max_files' => 30,
                ],
            ],
        ],
    ],
];
```

### Environment Variables

Add to your `.env` file:

```env
# Default log channel
MELLIVORA_LOG_CHANNEL=default

# Default log level
MELLIVORA_LOG_LEVEL=debug

# Email handler configuration (optional)
MELLIVORA_MAIL_TO=admin@example.com
MELLIVORA_MAIL_FROM=noreply@example.com
MELLIVORA_MAIL_SUBJECT="Application Error"
```

## 📖 Usage

### Helper Functions

The package provides convenient helper functions:

#### Basic Logging

```php
<?php

// Log to default channel
mlog('info', 'User logged in', ['user_id' => 123]);
mlog('error', 'Database connection failed');

// Log to specific channel
mlog_with('api', 'info', 'API request received', [
    'endpoint' => '/api/users',
    'method' => 'GET',
    'ip' => request()->ip(),
]);
```

#### Level-Specific Functions

```php
<?php

// Debug information
mlog_debug('Processing user data', ['user_id' => 123]);

// Info messages
mlog_info('User action completed', ['action' => 'profile_update']);

// Warning messages
mlog_warning('High memory usage detected', ['memory' => memory_get_usage()]);

// Error messages
mlog_error('Payment processing failed', ['order_id' => 456]);

// Critical errors
mlog_critical('System is down', ['component' => 'database']);

// Exception logging
try {
    // Some operation that might fail
    processPayment($order);
} catch (Exception $e) {
    mlog_exception($e, 'error', 'payment');
}
```

### Facade Usage

Use the `MLog` facade for more advanced operations:

```php
<?php

use Mellivora\Logger\Laravel\Facades\MLog;

// Basic logging
MLog::info('Application started');
MLog::error('System error occurred', ['component' => 'auth']);

// Channel-specific logging
MLog::logWith('api', 'debug', 'API debug message', [
    'request_id' => request()->header('X-Request-ID'),
    'user_id' => auth()->id(),
]);

// Exception logging with context
MLog::exception($exception, 'warning', 'validation', [
    'input' => $request->all(),
    'rules' => $validator->getRules(),
]);

// Get logger instance
$logger = MLog::getLogger('api');
$logger->info('Direct logger usage');
```

## 🎯 Practical Examples

### API Request Logging

Create a middleware for API request logging:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiRequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Log request start
        mlog_with('api', 'info', 'API request started', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ]);

        $response = $next($request);

        // Log request completion
        mlog_with('api', 'info', 'API request completed', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'user_id' => auth()->id(),
        ]);

        return $response;
    }
}
```

### Controller Usage

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mellivora\Logger\Laravel\Facades\MLog;

class UserController extends Controller
{
    public function store(Request $request)
    {
        try {
            mlog_with('api', 'info', 'Creating new user', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            $user = User::create($request->validated());

            mlog_with('api', 'info', 'User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json($user, 201);

        } catch (ValidationException $e) {
            mlog_with('api', 'warning', 'User creation validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            
            throw $e;

        } catch (Exception $e) {
            MLog::exception($e, 'error', 'api', [
                'action' => 'user_creation',
                'input' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
```

### Queue Job Logging

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $emailData;

    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    public function handle()
    {
        try {
            mlog_with('queue', 'info', 'Email job started', [
                'job' => 'ProcessEmailJob',
                'email' => $this->emailData['to'] ?? 'unknown',
                'queue' => $this->queue,
            ]);

            // Process email sending
            $this->sendEmail($this->emailData);

            mlog_with('queue', 'info', 'Email sent successfully', [
                'job' => 'ProcessEmailJob',
                'email' => $this->emailData['to'] ?? 'unknown',
            ]);

        } catch (Exception $e) {
            mlog_with('queue', 'error', 'Email job failed', [
                'job' => 'ProcessEmailJob',
                'email' => $this->emailData['to'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

## 🔧 Advanced Configuration

### Custom Handlers

```php
<?php

// config/mellivora-logger.php

return [
    'channels' => [
        'custom' => [
            'handlers' => [
                // File handler
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/custom.log'),
                    'level' => 'info',
                    'max_files' => 30,
                ],
                
                // Email handler for critical errors
                [
                    'type' => 'native_mailer',
                    'to' => env('MELLIVORA_MAIL_TO', 'admin@example.com'),
                    'from' => env('MELLIVORA_MAIL_FROM', 'noreply@example.com'),
                    'subject' => 'Critical Error Alert',
                    'level' => 'critical',
                ],
                
                // Slack handler (requires additional setup)
                [
                    'type' => 'slack',
                    'token' => env('SLACK_TOKEN'),
                    'channel' => env('SLACK_CHANNEL', '#alerts'),
                    'level' => 'error',
                ],
            ],
        ],
    ],
];
```

### Custom Processors

```php
<?php

// config/mellivora-logger.php

return [
    'channels' => [
        'api' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/api.log'),
                    'level' => 'info',
                ],
            ],
            'processors' => [
                'web_processor',      // Add web request information
                'memory_processor',   // Add memory usage
                'uid_processor',      // Add unique ID
            ],
        ],
    ],
];
```

## 🧪 Testing

### Testing with Logs

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mellivora\Logger\Laravel\Facades\MLog;

class UserControllerTest extends TestCase
{
    public function test_user_creation_logs_correctly()
    {
        // Mock the logger to capture log calls
        MLog::shouldReceive('logWith')
            ->once()
            ->with('api', 'info', 'Creating new user', \Mockery::type('array'));

        MLog::shouldReceive('logWith')
            ->once()
            ->with('api', 'info', 'User created successfully', \Mockery::type('array'));

        $response = $this->postJson('/api/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(201);
    }
}
```

## 🎯 Best Practices

### 1. Channel Organization

- **api**: API requests and responses
- **queue**: Background job processing
- **auth**: Authentication and authorization
- **payment**: Payment processing
- **default**: General application logs

### 2. Log Levels

- **debug**: Detailed debugging information
- **info**: General information about application flow
- **warning**: Warning conditions that should be noted
- **error**: Error conditions that should be investigated
- **critical**: Critical conditions requiring immediate attention

### 3. Context Information

Always include relevant context:

```php
mlog_with('api', 'info', 'User action', [
    'user_id' => auth()->id(),
    'action' => 'profile_update',
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'timestamp' => now()->toISOString(),
]);
```

### 4. Exception Handling

Use structured exception logging:

```php
try {
    // Risky operation
} catch (Exception $e) {
    mlog_exception($e, 'error', 'payment', [
        'order_id' => $order->id,
        'amount' => $order->amount,
        'payment_method' => $paymentMethod,
    ]);
    
    throw $e; // Re-throw if needed
}
```

## 🔍 Troubleshooting

### Common Issues

1. **Configuration not loaded**: Ensure you've published the config file
2. **Permissions**: Check write permissions for log directories
3. **Memory issues**: Use appropriate log levels to avoid excessive logging
4. **Performance**: Consider using queued logging for high-traffic applications

### Debug Mode

Enable debug logging in development:

```env
MELLIVORA_LOG_LEVEL=debug
```

## 📚 Additional Resources

- [Main Documentation](../README.md)
- [Testing Guide](TESTING.md)
- [API Reference](API.md)

---

**Languages**: [English](LARAVEL.md) | [中文](zh-CN/LARAVEL.md)
