<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\LoggerFactory;
use Mellivora\Logger\Logger;
use Mellivora\Logger\Processor\MemoryProcessor;
use Mellivora\Logger\Processor\WebProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class ComprehensiveCoverageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_comprehensive_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testLoggerWithAllLevels(): void
    {
        $logger = new Logger('comprehensive');
        $testHandler = new \Monolog\Handler\TestHandler();
        $logger->pushHandler($testHandler);

        // Test all log levels
        $logger->emergency('Emergency message');
        $logger->alert('Alert message');
        $logger->critical('Critical message');
        $logger->error('Error message');
        $logger->warning('Warning message');
        $logger->notice('Notice message');
        $logger->info('Info message');
        $logger->debug('Debug message');

        $records = $testHandler->getRecords();
        $this->assertCount(8, $records);

        // Verify all levels are recorded
        $levels = array_map(fn ($record) => $record['level']->value, $records);
        $expectedLevels = [
            Level::Emergency->value,
            Level::Alert->value,
            Level::Critical->value,
            Level::Error->value,
            Level::Warning->value,
            Level::Notice->value,
            Level::Info->value,
            Level::Debug->value,
        ];

        $this->assertEquals($expectedLevels, $levels);
    }

    public function testLoggerFilterChaining(): void
    {
        $logger = new Logger('filter_chain');
        $testHandler = new \Monolog\Handler\TestHandler();
        $logger->pushHandler($testHandler);

        // Add multiple filters
        $logger->pushFilter(function ($level, $message, $context) {
            return !str_contains($message, 'skip1');
        });

        $logger->pushFilter(function ($level, $message, $context) {
            return !str_contains($message, 'skip2');
        });

        $logger->pushFilter(function ($level, $message, $context) {
            return $level->value >= Level::Warning->value;
        });

        // These should pass all filters
        $logger->error('Error message');
        $logger->warning('Warning message');

        // These should be filtered out
        $logger->info('Info message'); // Filtered by level filter
        $logger->error('Error skip1 message'); // Filtered by first filter
        $logger->warning('Warning skip2 message'); // Filtered by second filter

        $records = $testHandler->getRecords();
        $this->assertCount(2, $records);
        $this->assertEquals('Error message', $records[0]['message']);
        $this->assertEquals('Warning message', $records[1]['message']);
    }

    public function testLoggerExceptionWithComplexContext(): void
    {
        $logger = new Logger('exception_test');
        $testHandler = new \Monolog\Handler\TestHandler();
        $logger->pushHandler($testHandler);

        $exception = new \RuntimeException('Complex exception', 0);
        $logger->addException($exception, Level::Critical);

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertEquals(Level::Critical, $record['level']);
        $this->assertEquals('Complex exception', $record['message']);
        $this->assertArrayHasKey('exception', $record['context']);
        $this->assertArrayHasKey('code', $record['context']);
        $this->assertArrayHasKey('file', $record['context']);
        $this->assertArrayHasKey('line', $record['context']);
        $this->assertArrayHasKey('trace', $record['context']);

        $this->assertEquals('RuntimeException', $record['context']['exception']);
        $this->assertIsInt($record['context']['code']);
        $this->assertIsString($record['context']['trace']);
    }

    public function testMemoryProcessorWithDifferentConfigurations(): void
    {
        // Test with real usage = false, formatting = false
        $processor1 = new MemoryProcessor(Level::Debug, false, false);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor1($record);
        $this->assertArrayHasKey('memory', $processedRecord->extra);
        $this->assertIsInt($processedRecord->extra['memory']);

        // Test with real usage = true, formatting = true
        $processor2 = new MemoryProcessor(Level::Debug, true, true);
        $processedRecord2 = $processor2($record);
        $this->assertArrayHasKey('memory', $processedRecord2->extra);
        $this->assertIsString($processedRecord2->extra['memory']);
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s*(B|KB|MB|GB)/', $processedRecord2->extra['memory']);

        // Test with real usage = true, formatting = false
        $processor3 = new MemoryProcessor(Level::Debug, true, false);
        $processedRecord3 = $processor3($record);
        $this->assertArrayHasKey('memory', $processedRecord3->extra);
        $this->assertIsInt($processedRecord3->extra['memory']);
    }

    public function testWebProcessorGetServerDataMethod(): void
    {
        // Backup original $_SERVER
        $originalServer = $_SERVER;

        // Set up test server data
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test User Agent',
            'HTTP_HOST' => 'test.example.com',
            'REQUEST_URI' => '/test/path?param=value',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            'HTTP_REFERER' => 'https://example.com/referer',
            'QUERY_STRING' => 'param=value&test=123',
            'SERVER_NAME' => 'test.example.com',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);
        $this->assertEquals('Test User Agent', $serverData['HTTP_USER_AGENT']);
        $this->assertEquals('test.example.com', $serverData['HTTP_HOST']);
        $this->assertEquals('/test/path?param=value', $serverData['REQUEST_URI']);
        $this->assertEquals('POST', $serverData['REQUEST_METHOD']);
        $this->assertEquals('192.168.1.100', $serverData['REMOTE_ADDR']);
        $this->assertEquals('10.0.0.1', $serverData['HTTP_X_FORWARDED_FOR']);
        $this->assertEquals('https://example.com/referer', $serverData['HTTP_REFERER']);
        $this->assertEquals('param=value&test=123', $serverData['QUERY_STRING']);
        $this->assertEquals('test.example.com', $serverData['SERVER_NAME']);
        $this->assertEquals('443', $serverData['SERVER_PORT']);
        $this->assertEquals('on', $serverData['HTTPS']);

        // Restore original $_SERVER
        $_SERVER = $originalServer;
    }

    public function testLoggerFactoryWithComplexHandlerChain(): void
    {
        $config = [
            'default' => 'complex_chain',
            'formatters' => [
                'detailed' => [
                    'class' => 'Monolog\Formatter\LineFormatter',
                    'params' => [
                        'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                        'dateFormat' => 'Y-m-d H:i:s.u',
                        'allowInlineLineBreaks' => true,
                        'ignoreEmptyContextAndExtra' => false,
                    ],
                ],
            ],
            'processors' => [
                'memory_detailed' => [
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
                'test_chain' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                        'bubble' => true,
                    ],
                    'formatter' => 'detailed',
                    'processors' => ['memory_detailed', 'cost_time', 'profiler'],
                ],
            ],
            'loggers' => [
                'complex_chain' => ['test_chain'],
            ],
        ];

        $factory = LoggerFactory::build($config);
        $logger = $factory->get('complex_chain');

        $this->assertInstanceOf(Logger::class, $logger);

        // Test that the logger works with all processors and formatters
        $logger->info('Complex chain test message', ['test_context' => 'value']);

        // Verify the logger has the expected handler
        $handler = $logger->getHandler(\Monolog\Handler\TestHandler::class);
        $this->assertInstanceOf(\Monolog\Handler\TestHandler::class, $handler);
    }

    public function testNamedRotatingFileHandlerWithDifferentDateFormats(): void
    {
        $dateFormats = ['Y-m-d', 'Y-m-d-H', 'Y-m-d-H-i', 'Ymd', 'Y-W'];

        foreach ($dateFormats as $dateFormat) {
            $filename = $this->tempDir . "/test_$dateFormat.log";
            $handler = new NamedRotatingFileHandler(
                filename: $filename,
                maxBytes: 1024,
                backupCount: 2,
                bufferSize: 0,
                dateFormat: $dateFormat,
                level: Level::Debug,
            );

            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'date_format_test',
                level: Level::Info,
                message: "Test message for date format: $dateFormat",
                context: [],
                extra: [],
            );

            // Test that handler can process the record without errors
            $handler->handle($record);
            $handler->close();

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        }
    }

    public function testLoggerFactoryMakeWithVariousHandlerCombinations(): void
    {
        $factory = new LoggerFactory([
            'handlers' => [
                'test1' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => ['level' => Level::Debug],
                ],
                'test2' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => ['level' => Level::Info],
                ],
                'null' => [
                    'class' => 'Monolog\Handler\NullHandler',
                    'params' => [],
                ],
            ],
        ]);

        // Test with single handler
        $logger1 = $factory->make('single', 'test1');
        $this->assertInstanceOf(Logger::class, $logger1);

        // Test with multiple handlers
        $logger2 = $factory->make('multiple', ['test1', 'test2']);
        $this->assertInstanceOf(Logger::class, $logger2);

        // Test with mixed existing and non-existing handlers
        $logger3 = $factory->make('mixed', ['test1', 'non_existent', 'null']);
        $this->assertInstanceOf(Logger::class, $logger3);

        // Test with empty handler list
        $logger4 = $factory->make('empty', []);
        $this->assertInstanceOf(Logger::class, $logger4);

        // Test with null handler
        $logger5 = $factory->make('null_handler', null);
        $this->assertInstanceOf(Logger::class, $logger5);
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
