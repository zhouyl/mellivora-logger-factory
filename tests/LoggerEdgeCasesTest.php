<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggerEdgeCasesTest extends TestCase
{
    private Logger $logger;

    private TestHandler $testHandler;

    protected function setUp(): void
    {
        $this->testHandler = new TestHandler();
        $this->logger = new Logger('edge_cases');
        $this->logger->pushHandler($this->testHandler);
    }

    public function testSetLevelWithAllValidTypes(): void
    {
        // Test with all Level enum values
        $levels = [
            Level::Emergency,
            Level::Alert,
            Level::Critical,
            Level::Error,
            Level::Warning,
            Level::Notice,
            Level::Info,
            Level::Debug,
        ];

        foreach ($levels as $level) {
            $this->logger->setLevel($level);
            $this->assertEquals($level, $this->logger->getLevel());
        }

        // Test with all valid string levels
        $stringLevels = [
            'emergency' => Level::Emergency,
            'alert' => Level::Alert,
            'critical' => Level::Critical,
            'error' => Level::Error,
            'warning' => Level::Warning,
            'notice' => Level::Notice,
            'info' => Level::Info,
            'debug' => Level::Debug,
        ];

        foreach ($stringLevels as $string => $expectedLevel) {
            $this->logger->setLevel($string);
            $this->assertEquals($expectedLevel, $this->logger->getLevel());
        }

        // Test with integer levels
        $intLevels = [
            600 => Level::Emergency,
            550 => Level::Alert,
            500 => Level::Critical,
            400 => Level::Error,
            300 => Level::Warning,
            250 => Level::Notice,
            200 => Level::Info,
            100 => Level::Debug,
        ];

        foreach ($intLevels as $int => $expectedLevel) {
            $this->logger->setLevel($int);
            $this->assertEquals($expectedLevel, $this->logger->getLevel());
        }
    }

    public function testSetLevelWithInvalidTypes(): void
    {
        // Test with array (invalid type)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid level type');
        $this->logger->setLevel([]);
    }

    public function testSetLevelWithInvalidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid level string');
        $this->logger->setLevel('invalid_level');
    }

    public function testSetLevelWithObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid level type');
        $this->logger->setLevel(new \stdClass());
    }

    public function testAddExceptionWithAllLevelTypes(): void
    {
        $exception = new \RuntimeException('Test exception', 0);

        // Test with Level enum
        $this->logger->addException($exception, Level::Critical);

        // Test with string level
        $this->logger->addException($exception, 'warning');

        $records = $this->testHandler->getRecords();
        $this->assertCount(2, $records);

        $this->assertEquals(Level::Critical, $records[0]['level']);
        $this->assertEquals(Level::Warning, $records[1]['level']);
    }

    public function testAddExceptionWithInvalidLevel(): void
    {
        $exception = new \RuntimeException('Test exception');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid level string');

        $this->logger->addException($exception, 'invalid_level');
    }

    public function testFilterStackOperations(): void
    {
        $filter1 = function ($level, $message, $context) { return true; };
        $filter2 = function ($level, $message, $context) { return false; };
        $filter3 = function ($level, $message, $context) { return !str_contains($message, 'skip'); };

        // Push multiple filters
        $this->logger->pushFilter($filter1);
        $this->logger->pushFilter($filter2);
        $this->logger->pushFilter($filter3);

        $filters = $this->logger->getFilters();
        $this->assertCount(3, $filters);

        // Filters should be in LIFO order (last pushed is first)
        $this->assertSame($filter3, $filters[0]);
        $this->assertSame($filter2, $filters[1]);
        $this->assertSame($filter1, $filters[2]);

        // Pop filters one by one
        $poppedFilter1 = $this->logger->popFilter();
        $this->assertSame($filter3, $poppedFilter1);

        $poppedFilter2 = $this->logger->popFilter();
        $this->assertSame($filter2, $poppedFilter2);

        $poppedFilter3 = $this->logger->popFilter();
        $this->assertSame($filter1, $poppedFilter3);

        // Stack should be empty now
        $this->assertCount(0, $this->logger->getFilters());
    }

    public function testFilterWithComplexLogic(): void
    {
        // Add a filter that only allows messages with specific context
        $this->logger->pushFilter(function ($level, $message, $context) {
            return isset($context['allowed']) && $context['allowed'] === true;
        });

        // This should be filtered out (no context)
        $this->logger->info('Message without context');

        // This should be filtered out (wrong context)
        $this->logger->info('Message with wrong context', ['allowed' => false]);

        // This should pass
        $this->logger->info('Message with correct context', ['allowed' => true]);

        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertEquals('Message with correct context', $records[0]['message']);
    }

    public function testHandlerManagementEdgeCases(): void
    {
        $handler1 = new TestHandler();
        $handler2 = new TestHandler();

        // Add multiple handlers of the same type
        $this->logger->pushHandler($handler1);
        $this->logger->pushHandler($handler2);

        // getHandler should return the first matching handler
        $retrievedHandler = $this->logger->getHandler(TestHandler::class);
        $this->assertSame($handler2, $retrievedHandler); // Last pushed is first

        // Remove handler should remove the first matching one
        $this->assertTrue($this->logger->removeHandler(TestHandler::class));

        // Now getHandler should return the other handler
        $retrievedHandler = $this->logger->getHandler(TestHandler::class);
        $this->assertSame($handler1, $retrievedHandler);

        // Remove the last handler
        $this->assertTrue($this->logger->removeHandler(TestHandler::class));

        // Now getHandler should return false
        $retrievedHandler = $this->logger->getHandler(TestHandler::class);
        $this->assertFalse($retrievedHandler);

        // Trying to remove non-existent handler should return false
        $this->assertFalse($this->logger->removeHandler(TestHandler::class));
    }

    public function testLoggerToStringWithDifferentNames(): void
    {
        $names = ['test', 'app', 'api', 'database', 'cache', '123', '', 'special-chars_test'];

        foreach ($names as $name) {
            $logger = new Logger($name);
            $expected = "Logger($name)";
            $this->assertEquals($expected, (string) $logger);
        }
    }

    public function testLoggerWithComplexContext(): void
    {
        $complexContext = [
            'user_id' => 123,
            'action' => 'login',
            'metadata' => [
                'ip' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
                'timestamp' => time(),
            ],
            'nested' => [
                'level1' => [
                    'level2' => [
                        'value' => 'deep_value',
                    ],
                ],
            ],
            'null_value' => null,
            'boolean_true' => true,
            'boolean_false' => false,
            'float_value' => 3.14159,
            'array_empty' => [],
            'string_empty' => '',
        ];

        $this->logger->info('Complex context test', $complexContext);

        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertEquals($complexContext, $records[0]['context']);
    }

    public function testLoggerWithProcessorChain(): void
    {
        // Add multiple processors
        $this->logger->pushProcessor(function ($record) {
            $record->extra['processor1'] = 'value1';
            return $record;
        });

        $this->logger->pushProcessor(function ($record) {
            $record->extra['processor2'] = 'value2';
            return $record;
        });

        $this->logger->pushProcessor(function ($record) {
            $record->extra['processor3'] = 'value3';
            return $record;
        });

        $this->logger->info('Processor chain test');

        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);

        $extra = $records[0]['extra'];
        $this->assertArrayHasKey('processor1', $extra);
        $this->assertArrayHasKey('processor2', $extra);
        $this->assertArrayHasKey('processor3', $extra);
        $this->assertEquals('value1', $extra['processor1']);
        $this->assertEquals('value2', $extra['processor2']);
        $this->assertEquals('value3', $extra['processor3']);
    }

    public function testLoggerLevelFilteringWithAllLevels(): void
    {
        // Set logger to Warning level
        $this->logger->setLevel(Level::Warning);

        // These should be recorded (>= Warning)
        $this->logger->emergency('Emergency');
        $this->logger->alert('Alert');
        $this->logger->critical('Critical');
        $this->logger->error('Error');
        $this->logger->warning('Warning');

        // These should be filtered out (< Warning)
        $this->logger->notice('Notice');
        $this->logger->info('Info');
        $this->logger->debug('Debug');

        $records = $this->testHandler->getRecords();
        $this->assertCount(5, $records);

        $messages = array_map(fn ($record) => $record['message'], $records);
        $this->assertEquals(['Emergency', 'Alert', 'Critical', 'Error', 'Warning'], $messages);
    }
}
