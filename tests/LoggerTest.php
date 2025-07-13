<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class LoggerTest extends TestCase
{
    protected Logger $logger;

    protected StreamHandler $handler;

    protected $stream;

    protected string|false $lastLogString;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLogger();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function testLevel(): void
    {
        $this->logger->setLevel(Level::Info);
        $this->assertSame(Level::Info, $this->logger->getLevel());

        $this->logger->warning('warning');
        $this->assertStringContainsString('warning', $this->lastLogString());

        $this->logger->debug('debug');
        $lastLog = $this->lastLogString();
        if ($lastLog !== false) {
            $this->assertStringNotContainsString('debug', $lastLog);
        } else {
            // If no log was written (filtered out), that's expected
            $this->assertTrue(true);
        }
    }

    public function testSetLevelWithString(): void
    {
        $this->logger->setLevel('error');
        $this->assertEquals(Level::Error, $this->logger->getLevel());

        $this->logger->setLevel('warning');
        $this->assertEquals(Level::Warning, $this->logger->getLevel());

        $this->logger->setLevel('info');
        $this->assertEquals(Level::Info, $this->logger->getLevel());

        $this->logger->setLevel('debug');
        $this->assertEquals(Level::Debug, $this->logger->getLevel());

        $this->logger->setLevel('notice');
        $this->assertEquals(Level::Notice, $this->logger->getLevel());

        $this->logger->setLevel('critical');
        $this->assertEquals(Level::Critical, $this->logger->getLevel());

        $this->logger->setLevel('alert');
        $this->assertEquals(Level::Alert, $this->logger->getLevel());

        $this->logger->setLevel('emergency');
        $this->assertEquals(Level::Emergency, $this->logger->getLevel());
    }

    public function testSetLevelWithInteger(): void
    {
        $this->logger->setLevel(300); // Warning level
        $this->assertEquals(Level::Warning, $this->logger->getLevel());

        $this->logger->setLevel(400); // Error level
        $this->assertEquals(Level::Error, $this->logger->getLevel());
    }

    public function testSetLevelWithInvalidType(): void
    {
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

    public function testAddException(): void
    {
        try {
            throw new \RuntimeException('test exception');
        } catch (\Exception $ex) {
            $this->logger->addException($ex);
        }
        $this->assertStringContainsString('RuntimeException', $this->lastLogString());
    }

    public function testHandler(): void
    {
        $handler = new NullHandler();
        $this->logger->pushHandler($handler);

        $this->assertSame($handler, $this->logger->getHandler(NullHandler::class));

        $this->assertTrue($this->logger->removeHandler(NullHandler::class));
        $this->assertFalse($this->logger->getHandler(NullHandler::class));

        // Test removing non-existent handler
        $this->assertFalse($this->logger->removeHandler('NonExistentHandler'));
    }

    public function testFilter(): void
    {
        $this->logger->pushFilter(function ($level, $message, $context) {
            return strpos($message, 'deny') === false;
        });
        $this->assertSame(1, count($this->logger->getFilters()));

        $this->logger->info('is deny msg');
        $lastLog = $this->lastLogString();
        if ($lastLog !== false) {
            $this->assertStringNotContainsString('deny', $lastLog);
        } else {
            // If no log was written (filtered out), that's expected
            $this->assertTrue(true);
        }

        $this->logger->popFilter();
        $this->assertSame(0, count($this->logger->getFilters()));

        $this->logger->info('is deny msg');
        $lastLog = $this->lastLogString();
        $this->assertNotFalse($lastLog);
        $this->assertStringContainsString('deny', $lastLog);
    }

    public function testFilterWithInvalidCallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filters must be valid callables');

        $this->logger->pushFilter('not_a_callable');
    }

    public function testPopFilterFromEmptyStack(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You tried to pop from an empty filter stack');

        $this->logger->popFilter();
    }

    public function testMultipleFilters(): void
    {
        $testHandler = new TestHandler();
        $this->logger->pushHandler($testHandler);

        // Filter out messages containing 'secret'
        $this->logger->pushFilter(function ($level, $message, $context) {
            return !str_contains(strtolower($message), 'secret');
        });

        // Filter out debug level messages
        $this->logger->pushFilter(function ($level, $message, $context) {
            return $level->value >= Level::Info->value;
        });

        $this->logger->debug('Debug message'); // Filtered by second filter
        $this->logger->info('Info message'); // Should pass
        $this->logger->info('secret info message'); // Filtered by first filter
        $this->logger->warning('Warning message'); // Should pass

        $records = $testHandler->getRecords();
        $this->assertCount(2, $records);
        $this->assertEquals('Info message', $records[0]['message']);
        $this->assertEquals('Warning message', $records[1]['message']);
    }

    public function testToString(): void
    {
        $this->assertSame('Logger(testcase)', $this->logger->__toString());
    }

    public function testLogWithContext(): void
    {
        $testHandler = new TestHandler();
        $this->logger->pushHandler($testHandler);

        $context = [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '192.168.1.1',
        ];

        $this->logger->info('User action', $context);

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertEquals($context, $records[0]['context']);
    }

    public function testLogWithProcessor(): void
    {
        $testHandler = new TestHandler();
        $this->logger->pushHandler($testHandler);

        // Add a processor that adds extra data
        $this->logger->pushProcessor(function ($record) {
            $record->extra['test_extra'] = 'extra_value';
            return $record;
        });

        $this->logger->info('Test message');

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertArrayHasKey('test_extra', $records[0]['extra']);
        $this->assertEquals('extra_value', $records[0]['extra']['test_extra']);
    }

    public function testLevelFiltering(): void
    {
        $testHandler = new TestHandler();
        $this->logger->pushHandler($testHandler);
        $this->logger->setLevel(Level::Warning);

        // This should be recorded (Warning >= Warning)
        $this->logger->warning('Warning message');

        // This should be filtered out (Info < Warning)
        $this->logger->info('Info message');

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertEquals('Warning message', $records[0]['message']);
    }

    protected function buildLogger(): void
    {
        $this->logger = new Logger('testcase');
        $this->stream = fopen('php://memory', 'a+');
        $this->handler = new StreamHandler($this->stream);

        $this->handler->setFormatter(new JsonFormatter());
        $this->logger->pushHandler($this->handler);
    }

    protected function lastLogString(): string|false
    {
        fseek($this->stream, 0);
        $log = fgets($this->stream);
        ftruncate($this->stream, 0);
        fseek($this->stream, 0);

        $this->lastLogString = $log ? trim($log) : false;

        return $this->lastLogString;
    }

    protected function lastLogJson(): mixed
    {
        return json_decode($this->lastLogString);
    }
}
