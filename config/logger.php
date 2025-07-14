<?php

declare(strict_types=1);

// formatters - Define the format for final log message output
$formatters = [
    // Simple message output
    'simple' => [
        'class' => Monolog\Formatter\LineFormatter::class,
        'params' => [
            'format' => "[%datetime%][%level_name%] %message% %context%\n",
        ],
    ],
    // Verbose message output with details
    'venbose' => [
        'class' => Monolog\Formatter\LineFormatter::class,
        'params' => [
            'format' => "[%datetime%][%channel%][%level_name%] %message% %context% %extra%\n",
        ],
    ],
    // JSON format output, convenient for ELK stack collection
    'json' => [
        'class' => Monolog\Formatter\JsonFormatter::class,
    ],
];

// processors - Registered processors will be attached to the extra field of messages
$processors = [
    // For logging file, line, class, method information where the log is output
    'intro' => [
        'class' => Monolog\Processor\IntrospectionProcessor::class,
        'params' => ['level' => 'ERROR', 'skipStackFramesCount' => 2],
    ],

    // For capturing HTTP web request header information
    'web' => [
        'class' => Mellivora\Logger\Processor\WebProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],

    // For capturing script execution information
    'script' => [
        'class' => Mellivora\Logger\Processor\ScriptProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],

    // For execution time cost analysis
    'cost' => [
        'class' => Mellivora\Logger\Processor\CostTimeProcessor::class,
        'params' => ['level' => 'DEBUG'],
    ],

    // For memory usage analysis
    'memory' => [
        'class' => Mellivora\Logger\Processor\MemoryProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],
];

// handlers - Configuration for message output methods
$handlers = [
    'file' => [
        'class' => 'Mellivora\Logger\Handler\NamedRotatingFileHandler',
        'params' => [
            'filename' => 'logs/%channel%.%date%.log',
            'maxBytes' => 100000000, // 100MB, maximum file size
            'backupCount' => 10, // Number of backup files to retain
            'bufferSize' => 10, // Buffer size (number of log entries)
            'dateFormat' => 'Y-m-d', // Date format
            'level' => 'INFO',
        ],
        'formatter' => 'json',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory'],
    ],
    'cli' => [
        'class' => 'Monolog\Handler\StreamHandler',
        'params' => [
            'stream' => 'php://stdout',
            'level' => 'DEBUG',
        ],
        'formatter' => 'simple',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory'],
    ],
    'mail' => [
        'class' => 'Mellivora\Logger\Handler\SmtpHandler',
        'params' => [
            'sender' => 'logger-factory <sender@mailhost.com>',
            'receivers' => [
                'receiver <receiver@mailhost.com>',
            ],
            'subject' => '[ERROR] FROM Logger-Factory',
            'certificates' => [
                'host' => 'smtp.mailhost.com',
                'port' => 25,
                'username' => 'sender@mailhost.com',
                'password' => 'sender-passwd',
            ],
            'maxRecords' => 10,
            'level' => 'CRITICAL',
        ],
        'formatter' => 'venbose',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory', 'memoryPeak'],
    ],
];

// loggers - When a declared logger is not in the following list, it defaults to 'default'
$loggers = [
    'default' => ['file', 'mail'],
    'cli' => ['cli', 'file', 'mail'],
    'exception' => ['file', 'mail'],
];

return [
    'formatters' => $formatters,
    'processors' => $processors,
    'handlers' => $handlers,
    'loggers' => $loggers,
];
