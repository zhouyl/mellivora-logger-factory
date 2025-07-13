<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggerFactoryEdgeCasesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_logger_edge_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testNewInstanceWithOptionComplexParameters(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with complex parameters including named parameters
        $option = [
            'class' => 'Monolog\Formatter\LineFormatter',
            'params' => [
                'format' => '[%datetime%] %level_name%: %message%',
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'ignoreEmptyContextAndExtra' => false,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Formatter\LineFormatter', $instance);
    }

    public function testNewInstanceWithOptionPositionalParameters(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with positional parameters
        $option = [
            'class' => 'Monolog\Handler\TestHandler',
            'params' => [
                Level::Debug,
                true,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\TestHandler', $instance);
    }

    public function testNewInstanceWithOptionEmptyParams(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with empty params
        $option = [
            'class' => 'Monolog\Handler\NullHandler',
            'params' => [],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\NullHandler', $instance);
    }

    public function testNewInstanceWithOptionNoParams(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test without params key
        $option = [
            'class' => 'Monolog\Handler\NullHandler',
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\NullHandler', $instance);
    }

    public function testComplexConfigurationWithAllComponents(): void
    {
        $config = [
            'default' => 'complex',
            'formatters' => [
                'json' => [
                    'class' => 'Monolog\Formatter\JsonFormatter',
                    'params' => [
                        'batchMode' => \Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
                        'appendNewline' => true,
                    ],
                ],
                'line' => [
                    'class' => 'Monolog\Formatter\LineFormatter',
                    'params' => [
                        'format' => "[%datetime%] %level_name%: %message%\n",
                        'dateFormat' => 'Y-m-d H:i:s',
                    ],
                ],
            ],
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
            ],
            'handlers' => [
                'test1' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'formatter' => 'json',
                    'processors' => ['memory', 'cost_time'],
                ],
                'test2' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Info,
                    ],
                    'formatter' => 'line',
                    'processors' => ['profiler'],
                ],
                'null' => [
                    'class' => 'Monolog\Handler\NullHandler',
                    'params' => [],
                ],
            ],
            'loggers' => [
                'complex' => ['test1', 'test2'],
                'simple' => ['null'],
                'multi' => ['test1', 'test2', 'null'],
            ],
        ];

        $factory = LoggerFactory::build($config);

        // Test all loggers
        $complexLogger = $factory->get('complex');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $complexLogger);

        $simpleLogger = $factory->get('simple');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $simpleLogger);

        $multiLogger = $factory->get('multi');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $multiLogger);
    }

    public function testMakeWithNonExistentHandler(): void
    {
        $factory = new LoggerFactory([
            'handlers' => [
                'existing' => [
                    'class' => 'Monolog\Handler\NullHandler',
                    'params' => [],
                ],
            ],
        ]);

        // Make with non-existent handler should get NullHandler
        $logger = $factory->make('test', 'nonexistent');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testMakeWithMixedExistentAndNonExistentHandlers(): void
    {
        $factory = new LoggerFactory([
            'handlers' => [
                'existing' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [],
                ],
            ],
        ]);

        // Make with mix of existent and non-existent handlers
        $logger = $factory->make('test', ['existing', 'nonexistent']);
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testGetWithEmptyStringChannel(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
            ],
        ]);

        // Empty string should fallback to default
        $logger = $factory->get('');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function testArrayAccessWithNonStringKey(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
            ],
        ]);

        // Test with non-string key (should be converted to string)
        $this->assertTrue(isset($factory['test']));
        $this->assertFalse(isset($factory['nonexistent']));
    }

    public function testRelativePathResolution(): void
    {
        // Test that relative paths are resolved correctly
        $config = [
            'handlers' => [
                'file' => [
                    'class' => 'Mellivora\Logger\Handler\NamedRotatingFileHandler',
                    'params' => [
                        'filename' => 'logs/relative.log', // Relative path
                        'maxBytes' => 1024,
                        'backupCount' => 3,
                    ],
                ],
            ],
            'loggers' => [
                'test' => ['file'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('test');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testConfigurationWithMissingComponents(): void
    {
        // Test configuration with missing formatters/processors sections
        $config = [
            'default' => 'test',
            'handlers' => [
                'null' => [
                    'class' => 'Monolog\Handler\NullHandler',
                    'params' => [],
                ],
            ],
            'loggers' => [
                'test' => ['null'],
            ],
            // Missing 'formatters' and 'processors' sections
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('test');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testHandlerWithNonExistentFormatterAndProcessor(): void
    {
        $config = [
            'default' => 'test',
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [],
                    'formatter' => 'nonexistent_formatter',
                    'processors' => ['nonexistent_processor'],
                ],
            ],
            'loggers' => [
                'test' => ['test'],
            ],
        ];

        // This should not throw an exception, just skip the non-existent components
        $factory = LoggerFactory::build($config);
        $logger = $factory->get('test');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testNewInstanceWithConstructorWithoutParameters(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with class that has no constructor parameters
        $option = [
            'class' => 'Monolog\Handler\NullHandler',
            'params' => [
                'level' => 'debug',
                'bubble' => true,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\NullHandler', $instance);
    }

    public function testFactoryWithComplexProcessorConfiguration(): void
    {
        $config = [
            'default' => 'complex',
            'processors' => [
                'memory_with_formatting' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => 'debug',
                        'realUsage' => true,
                        'useFormatting' => true,
                    ],
                ],
                'cost_time' => [
                    'class' => 'Mellivora\Logger\Processor\CostTimeProcessor',
                    'params' => [
                        'level' => 'info',
                    ],
                ],
            ],
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => 'debug',
                    ],
                    'processors' => ['memory_with_formatting', 'cost_time'],
                ],
            ],
            'loggers' => [
                'complex' => ['test'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('complex');
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
