<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggerFactoryComprehensiveTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_logger_comprehensive_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testCreateLoggerWithAllProcessorTypes(): void
    {
        $config = [
            'default' => 'comprehensive',
            'processors' => [
                'memory' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => Level::Debug,
                        'realUsage' => true,
                        'useFormatting' => true,
                    ],
                ],
                'cost_time' => [
                    'class' => 'Mellivora\Logger\Processor\CostTimeProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
                'profiler' => [
                    'class' => 'Mellivora\Logger\Processor\ProfilerProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
                'script' => [
                    'class' => 'Mellivora\Logger\Processor\ScriptProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
                'web' => [
                    'class' => 'Mellivora\Logger\Processor\WebProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
            ],
            'handlers' => [
                'comprehensive' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'processors' => ['memory', 'cost_time', 'profiler', 'script', 'web'],
                ],
            ],
            'loggers' => [
                'comprehensive' => ['comprehensive'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('comprehensive');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger can actually log
        $logger->info('Test message with all processors');
    }

    public function testCreateLoggerWithAllFormatterTypes(): void
    {
        $config = [
            'default' => 'formatter_test',
            'formatters' => [
                'line' => [
                    'class' => 'Monolog\Formatter\LineFormatter',
                    'params' => [
                        'format' => "[%datetime%] %level_name%: %message%\n",
                        'dateFormat' => 'Y-m-d H:i:s',
                        'allowInlineLineBreaks' => true,
                        'ignoreEmptyContextAndExtra' => false,
                    ],
                ],
                'json' => [
                    'class' => 'Monolog\Formatter\JsonFormatter',
                    'params' => [
                        'batchMode' => \Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
                        'appendNewline' => true,
                    ],
                ],
                'html' => [
                    'class' => 'Monolog\Formatter\HtmlFormatter',
                    'params' => [
                        'dateFormat' => 'Y-m-d H:i:s',
                    ],
                ],
            ],
            'handlers' => [
                'line_handler' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'formatter' => 'line',
                ],
                'json_handler' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'formatter' => 'json',
                ],
                'html_handler' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'formatter' => 'html',
                ],
            ],
            'loggers' => [
                'formatter_test' => ['line_handler', 'json_handler', 'html_handler'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('formatter_test');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger can actually log
        $logger->info('Test message with all formatters');
    }

    public function testCreateLoggerWithNamedRotatingFileHandler(): void
    {
        $config = [
            'default' => 'file_test',
            'handlers' => [
                'rotating_file' => [
                    'class' => 'Mellivora\Logger\Handler\NamedRotatingFileHandler',
                    'params' => [
                        'filename' => $this->tempDir . '/test.log',
                        'maxBytes' => 1024,
                        'backupCount' => 3,
                        'bufferSize' => 0,
                        'dateFormat' => 'Y-m-d',
                        'level' => Level::Debug,
                        'bubble' => true,
                        'filePermission' => 0o644,
                        'useLocking' => false,
                    ],
                ],
            ],
            'loggers' => [
                'file_test' => ['rotating_file'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('file_test');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger can actually log
        $logger->info('Test message to rotating file');
    }

    public function testFactoryWithEmptyConfiguration(): void
    {
        $config = [];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get();

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Should have default configuration
        $this->assertEquals('default', $factory->getDefault());
    }

    public function testFactoryWithMinimalConfiguration(): void
    {
        $config = [
            'loggers' => [
                'minimal' => [],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('minimal');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testFactoryWithStringLevelsInConfiguration(): void
    {
        $config = [
            'default' => 'string_levels',
            'processors' => [
                'memory' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => 'info', // String level
                        'realUsage' => true,
                        'useFormatting' => true,
                    ],
                ],
            ],
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => 'warning', // String level
                        'bubble' => true,
                    ],
                    'processors' => ['memory'],
                ],
            ],
            'loggers' => [
                'string_levels' => ['test'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('string_levels');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger can actually log
        $logger->error('Test message with string levels');
    }

    public function testFactoryGetWithNullChannel(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
            ],
        ]);

        // Test with null channel
        $logger = $factory->get(null);
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function testFactoryMakeWithEmptyHandlerName(): void
    {
        $factory = new LoggerFactory([
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [],
                ],
            ],
        ]);

        // Make with empty handler name
        $logger = $factory->make('test', '');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
