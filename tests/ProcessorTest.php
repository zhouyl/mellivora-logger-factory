<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Processor\CostTimeProcessor;
use Mellivora\Logger\Processor\MemoryProcessor;
use Mellivora\Logger\Processor\ProfilerProcessor;
use Mellivora\Logger\Processor\ScriptProcessor;
use Mellivora\Logger\Processor\WebProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    public function testCostTimeProcessor(): void
    {
        $processor = new CostTimeProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('cost', $processedRecord->extra);
        $this->assertIsFloat($processedRecord->extra['cost']);
    }

    public function testMemoryProcessor(): void
    {
        $processor = new MemoryProcessor(Level::Debug, true, true);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('memory', $processedRecord->extra);
        $this->assertIsString($processedRecord->extra['memory']);
        $this->assertStringContainsString('B', $processedRecord->extra['memory']);
    }

    public function testMemoryProcessorWithoutFormatting(): void
    {
        $processor = new MemoryProcessor(Level::Debug, true, false);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('memory', $processedRecord->extra);
        $this->assertIsInt($processedRecord->extra['memory']);
    }

    public function testProfilerProcessor(): void
    {
        $processor = new ProfilerProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('cost', $processedRecord->extra);
        $this->assertArrayHasKey('memory_usage', $processedRecord->extra);
        $this->assertArrayHasKey('memory_peak_usage', $processedRecord->extra);
    }

    public function testScriptProcessor(): void
    {
        if (php_sapi_name() !== 'cli') {
            $this->markTestSkipped('ScriptProcessor only works in CLI mode');
        }

        $processor = new ScriptProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('pid', $processedRecord->extra);
        $this->assertIsInt($processedRecord->extra['pid']);
    }

    public function testWebProcessor(): void
    {
        // Mock web environment
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
        $_SERVER['HTTP_REFERER'] = 'https://example.com/previous';

        $processor = new WebProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        // Skip if in CLI mode
        if (in_array(php_sapi_name(), ['cli', 'phpdbg'], true)) {
            $processedRecord = $processor($record);
            $this->assertEquals($record, $processedRecord);
            return;
        }

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('http_user_agent', $processedRecord->extra);
        $this->assertArrayHasKey('http_host', $processedRecord->extra);
        $this->assertArrayHasKey('request_uri', $processedRecord->extra);
        $this->assertArrayHasKey('request_method', $processedRecord->extra);
        $this->assertArrayHasKey('remote_addr', $processedRecord->extra);
    }

    public function testWebProcessorWithMissingServerVars(): void
    {
        // Clear server variables
        $originalServer = $_SERVER;
        $_SERVER = [];

        $processor = new WebProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        // Skip if in CLI mode
        if (in_array(php_sapi_name(), ['cli', 'phpdbg'], true)) {
            $processedRecord = $processor($record);
            $this->assertEquals($record, $processedRecord);
        }

        // Restore server variables
        $_SERVER = $originalServer;
    }

    public function testWebProcessorInCliMode(): void
    {
        // This test specifically checks CLI mode behavior
        if (!in_array(php_sapi_name(), ['cli', 'phpdbg'], true)) {
            $this->markTestSkipped('This test only runs in CLI mode');
        }

        $processor = new WebProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        // In CLI mode, the record should be returned unchanged
        $this->assertEquals($record, $processedRecord);
    }

    public function testProcessorLevelFiltering(): void
    {
        $processor = new CostTimeProcessor(Level::Error);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug, // Lower than processor level
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        // Should not add extra data when level is too low
        $this->assertEquals($record, $processedRecord);
    }

    public function testCostTimeProcessorMultipleCalls(): void
    {
        $processor = new CostTimeProcessor(Level::Debug);

        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'First message',
            context: [],
            extra: [],
        );

        $processedRecord1 = $processor($record1);
        $this->assertArrayHasKey('cost', $processedRecord1->extra);
        $firstCost = $processedRecord1->extra['cost'];

        // Add a small delay
        usleep(1000); // 1ms

        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Second message',
            context: [],
            extra: [],
        );

        $processedRecord2 = $processor($record2);
        $this->assertArrayHasKey('cost', $processedRecord2->extra);
        $secondCost = $processedRecord2->extra['cost'];

        // Second call should have a different (likely higher) cost
        $this->assertNotEquals($firstCost, $secondCost);
    }

    public function testMemoryProcessorFormatting(): void
    {
        // Test with formatting enabled
        $processor = new MemoryProcessor(Level::Debug, true, true);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('memory', $processedRecord->extra);
        $this->assertIsString($processedRecord->extra['memory']);
        $this->assertMatchesRegularExpression(
            '/\d+(\.\d+)?\s*(B|KB|MB|GB)/',
            $processedRecord->extra['memory'],
        );
    }

    public function testMemoryProcessorRealUsage(): void
    {
        // Test with real usage disabled
        $processor = new MemoryProcessor(Level::Debug, false, false);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('memory', $processedRecord->extra);
        $this->assertIsInt($processedRecord->extra['memory']);
    }

    public function testProfilerProcessorData(): void
    {
        $processor = new ProfilerProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('cost', $processedRecord->extra);
        $this->assertArrayHasKey('memory_usage', $processedRecord->extra);
        $this->assertArrayHasKey('memory_peak_usage', $processedRecord->extra);

        $this->assertIsFloat($processedRecord->extra['cost']);
        $this->assertIsString($processedRecord->extra['memory_usage']);
        $this->assertIsString($processedRecord->extra['memory_peak_usage']);

        // Verify memory format
        $this->assertMatchesRegularExpression(
            '/\d+(\.\d+)?\s*(B|KB|MB|GB)/',
            $processedRecord->extra['memory_usage'],
        );
        $this->assertMatchesRegularExpression(
            '/\d+(\.\d+)?\s*(B|KB|MB|GB)/',
            $processedRecord->extra['memory_peak_usage'],
        );
    }

    public function testScriptProcessorData(): void
    {
        if (php_sapi_name() !== 'cli') {
            $this->markTestSkipped('ScriptProcessor only works in CLI mode');
        }

        $processor = new ScriptProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('pid', $processedRecord->extra);
        $this->assertArrayHasKey('script', $processedRecord->extra);
        $this->assertArrayHasKey('command', $processedRecord->extra);

        $this->assertIsInt($processedRecord->extra['pid']);
        $this->assertIsString($processedRecord->extra['script']);
        $this->assertIsString($processedRecord->extra['command']);

        // PID should be positive
        $this->assertGreaterThan(0, $processedRecord->extra['pid']);
    }

    public function testScriptProcessorInNonCliMode(): void
    {
        if (php_sapi_name() === 'cli') {
            $this->markTestSkipped('This test only runs in non-CLI mode');
        }

        $processor = new ScriptProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        // In non-CLI mode, the record should be returned unchanged
        $this->assertEquals($record, $processedRecord);
    }

    public function testAllProcessorsWithHighLevel(): void
    {
        $processors = [
            new CostTimeProcessor(Level::Critical),
            new MemoryProcessor(Level::Critical),
            new ProfilerProcessor(Level::Critical),
            new ScriptProcessor(Level::Critical),
            new WebProcessor(Level::Critical),
        ];

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info, // Lower than processor level
            message: 'Test message',
            context: [],
            extra: [],
        );

        foreach ($processors as $processor) {
            $processedRecord = $processor($record);
            // Should not add extra data when level is too low
            $this->assertEquals($record, $processedRecord);
        }
    }
}
