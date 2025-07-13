<?php

declare(strict_types=1);

use Monolog\Level;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Logger Channel
    |--------------------------------------------------------------------------
    |
    | 默认的日志通道名称。当调用 mlog() 函数而不指定通道时，
    | 将使用此通道。
    |
    */
    'default' => env('MELLIVORA_LOG_CHANNEL', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Formatters
    |--------------------------------------------------------------------------
    |
    | 日志格式化器配置。定义如何格式化日志消息的输出格式。
    |
    */
    'formatters' => [
        'line' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'params' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'ignoreEmptyContextAndExtra' => false,
            ],
        ],
        'json' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'params' => [
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
                'appendNewline' => true,
            ],
        ],
        'html' => [
            'class' => Monolog\Formatter\HtmlFormatter::class,
            'params' => [
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processors
    |--------------------------------------------------------------------------
    |
    | 日志处理器配置。处理器用于在日志记录前添加额外的信息。
    |
    */
    'processors' => [
        'web' => [
            'class' => Mellivora\Logger\Processor\WebProcessor::class,
            'params' => [
                'level' => Level::Debug,
            ],
        ],
        'memory' => [
            'class' => Mellivora\Logger\Processor\MemoryProcessor::class,
            'params' => [
                'level' => Level::Debug,
                'realUsage' => true,
                'useFormatting' => true,
            ],
        ],
        'cost_time' => [
            'class' => Mellivora\Logger\Processor\CostTimeProcessor::class,
            'params' => [
                'level' => Level::Debug,
            ],
        ],
        'profiler' => [
            'class' => Mellivora\Logger\Processor\ProfilerProcessor::class,
            'params' => [
                'level' => Level::Debug,
            ],
        ],
        'script' => [
            'class' => Mellivora\Logger\Processor\ScriptProcessor::class,
            'params' => [
                'level' => Level::Debug,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handlers
    |--------------------------------------------------------------------------
    |
    | 日志处理器配置。定义日志的输出目标和处理方式。
    |
    */
    'handlers' => [
        'daily' => [
            'class' => Mellivora\Logger\Handler\NamedRotatingFileHandler::class,
            'params' => [
                'filename' => storage_path('logs/app.log'),
                'maxBytes' => 100 * 1024 * 1024, // 100MB
                'backupCount' => 30,
                'bufferSize' => 0,
                'dateFormat' => 'Y-m-d',
                'level' => Level::Debug,
                'bubble' => true,
            ],
            'formatter' => 'line',
            'processors' => ['memory', 'cost_time'],
        ],
        'single' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'params' => [
                'stream' => storage_path('logs/app.log'),
                'level' => Level::Debug,
                'bubble' => true,
            ],
            'formatter' => 'json',
            'processors' => ['web', 'memory'],
        ],
        'console' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'params' => [
                'stream' => 'php://stdout',
                'level' => Level::Info,
                'bubble' => true,
            ],
            'formatter' => 'line',
        ],
        'error' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'params' => [
                'stream' => storage_path('logs/app.error.log'),
                'level' => Level::Error,
                'bubble' => true,
            ],
            'formatter' => 'line',
            'processors' => ['web', 'memory', 'profiler'],
        ],
        'mail' => [
            'class' => Mellivora\Logger\Handler\SmtpHandler::class,
            'params' => [
                'sender' => env('MELLIVORA_LOG_MAIL_FROM', 'noreply@example.com'),
                'receivers' => [env('MELLIVORA_LOG_MAIL_TO', 'admin@example.com')],
                'subject' => env('MELLIVORA_LOG_MAIL_SUBJECT', 'Application Error'),
                'certificates' => [
                    'host' => env('MAIL_HOST', '127.0.0.1'),
                    'port' => (int) env('MAIL_PORT', 25),
                    'username' => env('MAIL_USERNAME'),
                    'password' => env('MAIL_PASSWORD'),
                ],
                'maxRecords' => 5,
                'level' => Level::Error,
                'bubble' => false,
            ],
            'formatter' => 'html',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logger Channels
    |--------------------------------------------------------------------------
    |
    | 日志通道配置。每个通道可以使用不同的处理器组合。
    |
    */
    'loggers' => [
        'app' => ['daily', 'console'],
        'api' => ['single', 'error'],
        'queue' => ['daily', 'console'],
        'database' => ['single'],
        'security' => ['error', 'mail'],
        'performance' => ['daily'],
        'debug' => ['console'],
    ],
];
