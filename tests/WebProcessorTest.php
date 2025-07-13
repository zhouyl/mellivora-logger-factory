<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Processor\WebProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class WebProcessorTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testWebProcessorInWebEnvironment(): void
    {
        // Mock web environment by temporarily changing php_sapi_name
        // Since we can't actually change php_sapi_name(), we'll test the processor
        // with the assumption that it's running in CLI mode but with web-like $_SERVER data

        $_SERVER = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/test/path',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            'HTTP_REFERER' => 'https://example.com/previous',
            'QUERY_STRING' => 'param=value',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
        ];

        $processor = new WebProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        // In CLI mode, the processor should return the record unchanged
        // But we can test that it doesn't throw exceptions
        $processedRecord = $processor($record);

        // In CLI mode, record should be unchanged
        if (in_array(php_sapi_name(), ['cli', 'phpdbg'], true)) {
            $this->assertEquals($record, $processedRecord);
        } else {
            // In web mode, extra data should be added
            $this->assertArrayHasKey('http_user_agent', $processedRecord->extra);
            $this->assertArrayHasKey('http_host', $processedRecord->extra);
            $this->assertArrayHasKey('request_uri', $processedRecord->extra);
            $this->assertArrayHasKey('request_method', $processedRecord->extra);
            $this->assertArrayHasKey('remote_addr', $processedRecord->extra);
        }
    }

    public function testWebProcessorWithPartialServerData(): void
    {
        // Test with only some server variables set
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            'REQUEST_METHOD' => 'POST',
            // Missing other variables
        ];

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

        // Should not throw exceptions even with missing server data
        $this->assertInstanceOf(LogRecord::class, $processedRecord);
    }

    public function testWebProcessorWithEmptyServerData(): void
    {
        // Test with empty $_SERVER
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

        $processedRecord = $processor($record);

        // Should not throw exceptions even with empty server data
        $this->assertInstanceOf(LogRecord::class, $processedRecord);
    }

    public function testWebProcessorLevelFiltering(): void
    {
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            'REQUEST_METHOD' => 'GET',
        ];

        $processor = new WebProcessor(Level::Error);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug, // Lower than processor level
            message: 'Test message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        // Should return unchanged record when level is too low
        $this->assertEquals($record, $processedRecord);
    }

    public function testWebProcessorWithHighLevelRecord(): void
    {
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/test',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $processor = new WebProcessor(Level::Debug);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error, // High level
            message: 'Error message',
            context: [],
            extra: [],
        );

        $processedRecord = $processor($record);

        // Should process the record (though in CLI mode it will be unchanged)
        $this->assertInstanceOf(LogRecord::class, $processedRecord);
    }

    public function testWebProcessorGetServerDataMethod(): void
    {
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/test',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            'HTTP_REFERER' => 'https://example.com/ref',
            'QUERY_STRING' => 'q=test',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to test the protected getServerData method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);

        // Check that all expected keys are present
        $expectedKeys = [
            'HTTP_USER_AGENT',
            'HTTP_HOST',
            'REQUEST_URI',
            'REQUEST_METHOD',
            'REMOTE_ADDR',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_REFERER',
            'QUERY_STRING',
            'SERVER_NAME',
            'SERVER_PORT',
            'HTTPS',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $serverData);
            $this->assertEquals($_SERVER[$key], $serverData[$key]);
        }
    }

    public function testWebProcessorWithMissingServerKeys(): void
    {
        // Test with server data that doesn't have all the keys the processor looks for
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            // Missing most other keys
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to test the protected getServerData method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);
        $this->assertArrayHasKey('HTTP_USER_AGENT', $serverData);
        $this->assertEquals('Test Agent', $serverData['HTTP_USER_AGENT']);

        // Missing keys should not be present in the result
        $this->assertArrayNotHasKey('HTTP_HOST', $serverData);
        $this->assertArrayNotHasKey('REQUEST_URI', $serverData);
    }

    public function testWebProcessorWithAllServerKeys(): void
    {
        // Test with all possible server keys
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test)',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/test?param=value',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 192.168.1.1',
            'HTTP_REFERER' => 'https://example.com/previous',
            'QUERY_STRING' => 'param=value&other=test',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
            'HTTP_CONNECTION' => 'keep-alive',
            'HTTP_CACHE_CONTROL' => 'no-cache',
            'HTTP_PRAGMA' => 'no-cache',
            'HTTP_AUTHORIZATION' => 'Bearer token123',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '123',
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to test the protected getServerData method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);

        // Check that expected keys are present
        $expectedKeys = [
            'HTTP_USER_AGENT',
            'HTTP_HOST',
            'REQUEST_URI',
            'REQUEST_METHOD',
            'REMOTE_ADDR',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_REFERER',
            'QUERY_STRING',
            'SERVER_NAME',
            'SERVER_PORT',
            'HTTPS',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $serverData);
            $this->assertEquals($_SERVER[$key], $serverData[$key]);
        }
    }

    public function testWebProcessorWithSpecialCharacters(): void
    {
        // Test with special characters in server data
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test) "Special" <Characters>',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/test?param=value&special=%20%21%40%23',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.100',
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to test the protected getServerData method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);
        $this->assertArrayHasKey('HTTP_USER_AGENT', $serverData);
        $this->assertEquals('Mozilla/5.0 (Test) "Special" <Characters>', $serverData['HTTP_USER_AGENT']);
        $this->assertArrayHasKey('REQUEST_URI', $serverData);
        $this->assertEquals('/api/test?param=value&special=%20%21%40%23', $serverData['REQUEST_URI']);
    }

    public function testWebProcessorWithNullValues(): void
    {
        // Test with null values in server data
        $_SERVER = [
            'HTTP_USER_AGENT' => null,
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => null,
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.100',
        ];

        $processor = new WebProcessor(Level::Debug);

        // Use reflection to test the protected getServerData method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);

        // Null values should still be included
        $this->assertArrayHasKey('HTTP_USER_AGENT', $serverData);
        $this->assertNull($serverData['HTTP_USER_AGENT']);
        $this->assertArrayHasKey('REQUEST_URI', $serverData);
        $this->assertNull($serverData['REQUEST_URI']);
    }

    public function testWebProcessorWithDifferentLevels(): void
    {
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Test Agent',
            'HTTP_HOST' => 'example.com',
        ];

        // Test with different processor levels
        $levels = [Level::Debug, Level::Info, Level::Notice, Level::Warning, Level::Error];

        foreach ($levels as $processorLevel) {
            $processor = new WebProcessor($processorLevel);

            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Error, // High level that should always pass
                message: 'Test message',
                context: [],
                extra: [],
            );

            $processedRecord = $processor($record);

            // Should process the record since Error >= any processor level
            $this->assertInstanceOf(LogRecord::class, $processedRecord);
        }
    }

    public function testWebProcessorGetServerDataWithEmptyValues(): void
    {
        // Test with empty string values
        $_SERVER = [
            'HTTP_USER_AGENT' => '',
            'HTTP_HOST' => '',
            'REQUEST_URI' => '',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '',
        ];

        $processor = new WebProcessor(Level::Debug);

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);
        $this->assertEquals('', $serverData['HTTP_USER_AGENT']);
        $this->assertEquals('', $serverData['HTTP_HOST']);
        $this->assertEquals('', $serverData['REQUEST_URI']);
        $this->assertEquals('GET', $serverData['REQUEST_METHOD']);
        $this->assertEquals('', $serverData['REMOTE_ADDR']);
    }

    public function testWebProcessorGetServerDataWithOnlyRequiredKeys(): void
    {
        // Test with only some keys present
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $processor = new WebProcessor(Level::Debug);

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getServerData');
        $method->setAccessible(true);

        $serverData = $method->invoke($processor);

        $this->assertIsArray($serverData);
        $this->assertArrayHasKey('REQUEST_METHOD', $serverData);
        $this->assertArrayHasKey('REMOTE_ADDR', $serverData);
        $this->assertEquals('POST', $serverData['REQUEST_METHOD']);
        $this->assertEquals('127.0.0.1', $serverData['REMOTE_ADDR']);

        // Keys that are not in $_SERVER should not be in the result
        $this->assertArrayNotHasKey('HTTP_USER_AGENT', $serverData);
        $this->assertArrayNotHasKey('HTTP_HOST', $serverData);
    }
}
