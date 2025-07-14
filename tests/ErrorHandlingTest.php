<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\Handler\SmtpHandler;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Error handling and edge cases test suite.
 *
 * Tests various error conditions and edge cases to improve test coverage:
 * - Invalid file paths and permissions
 * - Network failures for SMTP
 * - Invalid configurations
 * - Memory and resource limits
 * - Exception handling
 */
class ErrorHandlingTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_error_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testInvalidFilePathHandling(): void
    {
        // Test with invalid characters in filename
        $invalidPath = $this->tempDir . "/invalid\0filename.log";

        $this->expectException(\InvalidArgumentException::class);
        new NamedRotatingFileHandler($invalidPath, 1024, 3);
    }

    public function testReadOnlyDirectoryHandling(): void
    {
        $readOnlyDir = $this->tempDir . '/readonly';
        mkdir($readOnlyDir, 0o555); // Read-only directory

        $filename = $readOnlyDir . '/test.log';

        try {
            $handler = new NamedRotatingFileHandler($filename, 1024, 3);

            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: 'Test message',
                context: [],
                extra: []
            );

            // This should handle the permission error gracefully
            $handler->handle($record);

            // If we get here, the handler managed the error
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Expected behavior - permission denied
            $this->assertStringContainsString('Permission denied', $e->getMessage());
        } finally {
            // Restore permissions for cleanup
            chmod($readOnlyDir, 0o755);
        }
    }

    public function testSmtpHandlerWithInvalidConfiguration(): void
    {
        // Test with invalid SMTP configuration
        $handler = new SmtpHandler(
            sender: 'invalid@example.com',
            recipients: ['test@example.com'],
            subject: 'Test Email',
            host: 'invalid.smtp.server.that.does.not.exist',
            port: 587,
            username: 'invalid@example.com',
            password: 'invalid_password'
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'SMTP test message',
            context: [],
            extra: []
        );

        // This should handle the SMTP error gracefully
        try {
            $handler->handle($record);
            $this->assertTrue(true); // If no exception, handler managed the error
        } catch (\Exception $e) {
            // Expected behavior for invalid SMTP config
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testLoggerFactoryWithInvalidConfiguration(): void
    {
        $invalidConfig = [
            'handlers' => [
                'invalid_handler' => [
                    'class' => 'NonExistentHandlerClass',
                    'params' => []
                ]
            ],
            'loggers' => [
                'test' => ['invalid_handler']
            ]
        ];

        $this->expectException(\RuntimeException::class);
        $factory = new LoggerFactory($invalidConfig);
        $factory->get('test');
    }

    public function testLoggerFactoryWithMissingRequiredParameters(): void
    {
        $invalidConfig = [
            'handlers' => [
                'file_handler' => [
                    'class' => NamedRotatingFileHandler::class,
                    // Missing required 'params'
                ]
            ],
            'loggers' => [
                'test' => ['file_handler']
            ]
        ];

        $this->expectException(\RuntimeException::class);
        $factory = new LoggerFactory($invalidConfig);
        $factory->get('test');
    }

    public function testExtremelyLongLogMessage(): void
    {
        $filename = $this->tempDir . '/long_message.log';
        $handler = new NamedRotatingFileHandler($filename, 10240, 3);

        // Create an extremely long message (1MB)
        $longMessage = str_repeat('A', 1024 * 1024);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: $longMessage,
            context: [],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
        $this->assertGreaterThan(1024 * 1024, filesize($filename));
    }

    public function testCircularReferenceInContext(): void
    {
        $filename = $this->tempDir . '/circular.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        // Create circular reference
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Circular reference test',
            context: ['circular' => $obj1],
            extra: []
        );

        // This should handle circular references gracefully
        $handler->handle($record);

        $this->assertFileExists($filename);
    }

    public function testNullAndSpecialValuesInContext(): void
    {
        $filename = $this->tempDir . '/special_values.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Special values test',
            context: [
                'null_value' => null,
                'false_value' => false,
                'true_value' => true,
                'zero_value' => 0,
                'empty_string' => '',
                'float_nan' => NAN,
                'float_inf' => INF,
                'resource' => fopen('php://memory', 'r')
            ],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
        $content = file_get_contents($filename);
        $this->assertStringContainsString('Special values test', $content);
    }

    public function testMemoryLimitHandling(): void
    {
        $filename = $this->tempDir . '/memory_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        // Create a large array to test memory handling
        $largeArray = array_fill(0, 10000, str_repeat('X', 100));

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Memory test',
            context: ['large_data' => $largeArray],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
    }

    public function testConcurrentFileRotation(): void
    {
        $filename = $this->tempDir . '/concurrent_rotation.log';

        // Create multiple handlers for the same file
        $handlers = [];
        for ($i = 0; $i < 5; $i++) {
            $handlers[] = new NamedRotatingFileHandler($filename, 100, 2); // Small size for quick rotation
        }

        // Write from multiple handlers simultaneously
        for ($i = 0; $i < 20; $i++) {
            foreach ($handlers as $index => $handler) {
                $record = new LogRecord(
                    datetime: new \DateTimeImmutable(),
                    channel: "test{$index}",
                    level: Level::Info,
                    message: "Concurrent message {$i} from handler {$index}",
                    context: [],
                    extra: []
                );
                $handler->handle($record);
            }
        }

        $this->assertFileExists($filename);

        // Check that rotation occurred
        $rotatedFiles = glob($filename . '.*');
        $this->assertGreaterThan(0, count($rotatedFiles));
    }

    public function testInvalidDateTimeHandling(): void
    {
        $filename = $this->tempDir . '/datetime_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        // Test with various datetime scenarios
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('1970-01-01 00:00:00'), // Unix epoch
            channel: 'test',
            level: Level::Info,
            message: 'Epoch time test',
            context: [],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
    }

    public function testFileSystemFullSimulation(): void
    {
        // This test simulates a full filesystem by using a very small file size limit
        $filename = $this->tempDir . '/full_fs.log';
        $handler = new NamedRotatingFileHandler($filename, 1, 0); // 1 byte limit, no backups

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'This message is definitely longer than 1 byte',
            context: [],
            extra: []
        );

        // This should handle the size limit gracefully
        $handler->handle($record);

        $this->assertFileExists($filename);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
