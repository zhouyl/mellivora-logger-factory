<?php

declare(strict_types=1);

use Monolog\Level;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Logger Channel
    |--------------------------------------------------------------------------
    |
    | The default log channel name. When calling the mlog() function without
    | specifying a channel, this channel will be used.
    |
    */
    'default' => env('MELLIVORA_LOG_CHANNEL', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Formatters
    |--------------------------------------------------------------------------
    |
    | Log formatter configuration. Defines how to format the output of log messages.
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
    | Log processor configuration. Processors are used to add additional
    | information before logging.
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
    | Log handler configuration. Defines the output targets and processing
    | methods for logs.
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
    | Logger channel configuration. Each channel can use different combinations
    | of handlers.
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
