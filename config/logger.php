<?php

// formatters - 用于最终输出日志消息的格式
$formatters = [
    // 简单消息输出
    'simple' => [
        'class'  => Monolog\Formatter\LineFormatter::class,
        'params' => [
            'format' => "[%datetime%][%level_name%] %message% %context%\n",
        ],
    ],
    // 输出消息详情
    'venbose' => [
        'class'  => Monolog\Formatter\LineFormatter::class,
        'params' => [
            'format' => "[%datetime%][%channel%][%level_name%] %message% %context% %extra%\n",
        ],
    ],
    // JSON 格式输出，便于 ELK 收集
    'json' => [
        'class' => Monolog\Formatter\JsonFormatter::class,
    ],
];

// processors - 注册的 processor 将会附加在消息的 extra 字段中
$processors = [
    // 用于日志输出所在 的 file, line, class, method, ...
    'intro' => [
        'class'  => Monolog\Processor\IntrospectionProcessor::class,
        'params' => ['level' => 'ERROR', 'skipStackFramesCount' => 2],
    ],

    // 用于捕获 http web 请求头信息
    'web' => [
        'class'  => Mellivora\Logger\Processor\WebProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],

    // 用于捕获脚本运行信息
    'script' => [
        'class'  => Mellivora\Logger\Processor\ScriptProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],

    // 用于时间成本分析
    'cost' => [
        'class'  => Mellivora\Logger\Processor\CostTimeProcessor::class,
        'params' => ['level' => 'DEBUG'],
    ],

    // 用于内存使用情况分析
    'memory' => [
        'class'  => Mellivora\Logger\Processor\MemoryProcessor::class,
        'params' => ['level' => 'ERROR'],
    ],
];

// handlers - 用于消息输出方式的设定
$handlers = [
    'file' => [
        'class'  => 'Mellivora\Logger\Handler\NamedRotatingFileHandler',
        'params' => [
            'filename'    => 'logs/%channel%.%date%.log',
            'maxBytes'    => 100000000, // 100Mb，文件最大尺寸
            'backupCount' => 10, // 文件保留数量
            'bufferSize'  => 10, // 缓冲区大小(日志数量)
            'dateFormat'  => 'Y-m-d', // 日期格式
            'level'       => 'INFO',
        ],
        'formatter'  => 'json',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory'],
    ],
    'cli' => [
        'class'  => 'Monolog\Handler\StreamHandler',
        'params' => [
            'stream' => 'php://stdout',
            'level'  => 'DEBUG',
        ],
        'formatter'  => 'simple',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory'],
    ],
    'mail' => [
        'class'  => 'Mellivora\Logger\Handler\SmtpHandler',
        'params' => [
            'sender'     => 'logger-factory <sender@mailhost.com>',
            'receivers'  => [
                'receiver <receiver@mailhost.com>',
            ],
            'subject'      => '[ERROR] FROM Logger-Factory',
            'certificates' => [
                'host'     => 'smtp.mailhost.com',
                'port'     => 25,
                'username' => 'sender@mailhost.com',
                'password' => 'sender-passwd',
            ],
            'maxRecords' => 10,
            'level'      => 'CRITICAL',
        ],
        'formatter'  => 'venbose',
        'processors' => ['intro', 'web', 'script', 'cost', 'memory', 'memoryPeak'],
    ],
];

// loggers -  当声明的 logger 不在以下列表中时，默认为 default
$loggers = [
    'default'   => ['file', 'mail'],
    'cli'       => ['cli', 'file', 'mail'],
    'exception' => ['file', 'mail'],
];

return [
    'formatters' => $formatters,
    'processors' => $processors,
    'handlers'   => $handlers,
    'loggers'    => $loggers,
];
