<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggerFactoryAdvancedTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_logger_advanced_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testNewInstanceWithReflectionEdgeCases(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with class that has optional parameters
        $option = [
            'class' => 'Monolog\Formatter\LineFormatter',
            'params' => [
                'format' => '[%datetime%] %message%',
                // Missing other optional parameters
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Formatter\LineFormatter', $instance);
    }

    public function testNewInstanceWithMixedParameterTypes(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with mixed parameter types
        $option = [
            'class' => 'Monolog\Handler\TestHandler',
            'params' => [
                'level' => Level::Debug,
                'bubble' => true,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\TestHandler', $instance);
    }

    public function testNewInstanceWithPositionalAndNamedParams(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with both positional and named parameters
        $option = [
            'class' => 'Monolog\Formatter\LineFormatter',
            'params' => [
                0 => '[%datetime%] %message%', // Positional
                'dateFormat' => 'Y-m-d H:i:s',  // Named
                1 => 'Y-m-d H:i:s',             // Positional (should be ignored due to named)
                'allowInlineLineBreaks' => true, // Named
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Formatter\LineFormatter', $instance);
    }

    public function testNewInstanceWithNullParameters(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with null parameters
        $option = [
            'class' => 'Monolog\Handler\TestHandler',
            'params' => [
                'level' => null,
                'bubble' => null,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\TestHandler', $instance);
    }

    public function testFactoryWithComplexNestedConfiguration(): void
    {
        $config = [
            'default' => 'nested',
            'formatters' => [
                'complex' => [
                    'class' => 'Monolog\Formatter\LineFormatter',
                    'params' => [
                        'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                        'dateFormat' => 'Y-m-d H:i:s.u',
                        'allowInlineLineBreaks' => true,
                        'ignoreEmptyContextAndExtra' => false,
                        'includeStacktraces' => true,
                    ],
                ],
            ],
            'processors' => [
                'all_processors' => [
                    'class' => 'Mellivora\Logger\Processor\ProfilerProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
            ],
            'handlers' => [
                'complex_handler' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                        'bubble' => true,
                    ],
                    'formatter' => 'complex',
                    'processors' => ['all_processors'],
                ],
            ],
            'loggers' => [
                'nested' => ['complex_handler'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('nested');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger actually works
        $logger->info('Complex nested configuration test', ['test' => 'data']);
    }

    public function testFactoryWithEmptyHandlersArray(): void
    {
        $config = [
            'default' => 'empty_handlers',
            'loggers' => [
                'empty_handlers' => [], // Empty handlers array
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('empty_handlers');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testFactoryWithNonExistentHandlerInLogger(): void
    {
        $config = [
            'default' => 'non_existent',
            'handlers' => [
                'existing' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [],
                ],
            ],
            'loggers' => [
                'non_existent' => ['existing', 'non_existent_handler'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('non_existent');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);
    }

    public function testFactoryMakeWithComplexHandlerConfiguration(): void
    {
        $config = [
            'formatters' => [
                'test_formatter' => [
                    'class' => 'Monolog\Formatter\JsonFormatter',
                    'params' => [],
                ],
            ],
            'processors' => [
                'test_processor' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
            ],
            'handlers' => [
                'complex' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Info,
                    ],
                    'formatter' => 'test_formatter',
                    'processors' => ['test_processor'],
                ],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->make('test_make', ['complex']);

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger works
        $logger->info('Make with complex handler test');
    }

    public function testFactoryWithHandlerHavingNoFormatterOrProcessors(): void
    {
        $config = [
            'default' => 'simple',
            'handlers' => [
                'simple' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    // No formatter or processors
                ],
            ],
            'loggers' => [
                'simple' => ['simple'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('simple');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test that the logger works without formatter/processors
        $logger->info('Simple handler test');
    }

    public function testFactoryGetWithNullAndEmptyString(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
            ],
        ]);

        // Test with null
        $logger1 = $factory->get(null);
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger1);

        // Test with empty string
        $logger2 = $factory->get('');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger2);

        // Both should return the same default logger
        $this->assertEquals($factory->getDefault(), 'test');
    }

    public function testFactoryArrayAccessWithVariousTypes(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
                '123' => [], // Numeric string
                '0' => [],   // Zero string
            ],
        ]);

        // Test with string keys
        $this->assertTrue(isset($factory['test']));
        $this->assertTrue(isset($factory['123']));
        $this->assertTrue(isset($factory['0']));

        // Test with non-existent keys
        $this->assertFalse(isset($factory['non_existent']));
        $this->assertFalse(isset($factory['']));
    }

    public function testFactoryReleaseAndRecreation(): void
    {
        $factory = new LoggerFactory([
            'default' => 'test',
            'loggers' => [
                'test' => [],
            ],
        ]);

        // Get a logger
        $logger1 = $factory->get('test');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger1);

        // Release all loggers
        $factory->release();

        // Get the same logger again - should be a new instance
        $logger2 = $factory->get('test');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger2);

        // They should be different instances
        $this->assertNotSame($logger1, $logger2);
    }

    public function testFactoryWithProcessorHavingDifferentLevels(): void
    {
        $config = [
            'default' => 'level_test',
            'processors' => [
                'debug_processor' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                ],
                'error_processor' => [
                    'class' => 'Mellivora\Logger\Processor\CostTimeProcessor',
                    'params' => [
                        'level' => Level::Error,
                    ],
                ],
            ],
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'processors' => ['debug_processor', 'error_processor'],
                ],
            ],
            'loggers' => [
                'level_test' => ['test'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('level_test');

        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger);

        // Test with different log levels
        $logger->debug('Debug message');   // Should trigger debug_processor
        $logger->error('Error message');   // Should trigger both processors
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
