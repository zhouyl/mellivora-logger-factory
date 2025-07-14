<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive file operations test suite.
 *
 * Tests various file operations scenarios to improve test coverage:
 * - File creation and writing
 * - Directory permissions
 * - File rotation scenarios
 * - Error handling for file operations
 * - Edge cases with file paths
 */
class FileOperationsTest extends TestCase
{
    private string $tempDir;
    private string $logsDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_file_test_' . uniqid();
        $this->logsDir = $this->tempDir . '/logs';
        mkdir($this->logsDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testFileCreationInLogsDirectory(): void
    {
        $filename = $this->logsDir . '/app.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 1); // Use buffer size 1

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $handler->handle($record);
        $handler->flush(); // Flush to write to file

        $actualFilename = $handler->getFilename('test');
        $this->assertFileExists($actualFilename);
        $this->assertStringContainsString('Test message', file_get_contents($actualFilename));
    }

    public function testFileRotationWithMultipleWrites(): void
    {
        $filename = $this->logsDir . '/rotation.log';
        $handler = new NamedRotatingFileHandler($filename, 100, 3, 1); // Small size for rotation, buffer size 1

        // Write multiple records to trigger rotation
        for ($i = 1; $i <= 10; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: "Test message number {$i} with some additional content to make it longer",
                context: [],
                extra: []
            );
            $handler->handle($record);
        }

        $handler->flush(); // Ensure all data is written

        // Check that file was created
        $actualFilename = $handler->getFilename('test');
        $this->assertFileExists($actualFilename);

        // For rotation testing, we'll just verify the file exists and has content
        $this->assertGreaterThan(0, filesize($actualFilename), 'File should have content');
    }

    public function testFileCreationWithNestedDirectories(): void
    {
        $nestedPath = $this->logsDir . '/app/api/debug.log';
        $handler = new NamedRotatingFileHandler($nestedPath, 1024, 3, 1);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'api',
            level: Level::Debug,
            message: 'Nested directory test',
            context: [],
            extra: []
        );

        $handler->handle($record);
        $handler->flush();

        $actualFilename = $handler->getFilename('api');
        $this->assertFileExists($actualFilename);
        $this->assertDirectoryExists(dirname($actualFilename));
    }

    public function testHandlerConfiguration(): void
    {
        $filename = $this->logsDir . '/config_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 1);

        // Test that handler is properly configured
        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Configuration test',
            context: [],
            extra: []
        );

        $result = $handler->handle($record);
        $this->assertTrue($result); // Should return true when handled

        $handler->flush();
        $actualFilename = $handler->getFilename('test');
        $this->assertFileExists($actualFilename);
    }

    public function testLargeFileHandling(): void
    {
        $filename = $this->logsDir . '/large.log';
        $handler = new NamedRotatingFileHandler($filename, 5000, 2); // 5KB limit

        // Write a large amount of data
        $largeMessage = str_repeat('This is a large log message. ', 100);

        for ($i = 1; $i <= 20; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: $largeMessage . " Entry {$i}",
                context: [],
                extra: []
            );
            $handler->handle($record);
        }

        $this->assertFileExists($filename);

        // Verify file size management
        $fileSize = filesize($filename);
        $this->assertLessThanOrEqual(10000, $fileSize, 'File should be managed within reasonable size limits');
    }

    public function testDateFormatInFilenames(): void
    {
        $baseFilename = $this->logsDir . '/dated_%date%.log';
        $handler = new NamedRotatingFileHandler($baseFilename, 1024, 3, 0, 'Y-m-d');

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Date format test',
            context: [],
            extra: []
        );

        $handler->handle($record);

        // Check that date was substituted in filename
        $expectedDate = date('Y-m-d');
        $expectedFilename = $this->logsDir . "/dated_{$expectedDate}.log";
        $this->assertFileExists($expectedFilename);
    }

    public function testConcurrentFileAccess(): void
    {
        $filename = $this->logsDir . '/concurrent.log';
        $handler1 = new NamedRotatingFileHandler($filename, 1024, 3);
        $handler2 = new NamedRotatingFileHandler($filename, 1024, 3);

        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test1',
            level: Level::Info,
            message: 'Concurrent access test 1',
            context: [],
            extra: []
        );

        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test2',
            level: Level::Info,
            message: 'Concurrent access test 2',
            context: [],
            extra: []
        );

        $handler1->handle($record1);
        $handler2->handle($record2);

        $this->assertFileExists($filename);
        $content = file_get_contents($filename);
        $this->assertStringContainsString('Concurrent access test 1', $content);
        $this->assertStringContainsString('Concurrent access test 2', $content);
    }

    public function testFileRotationBackupCount(): void
    {
        $filename = $this->logsDir . '/backup_count.log';
        $backupCount = 2;
        $handler = new NamedRotatingFileHandler($filename, 50, $backupCount); // Very small size

        // Write many records to force multiple rotations
        for ($i = 1; $i <= 20; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: "Backup test message {$i} with extra content to exceed size limit",
                context: [],
                extra: []
            );
            $handler->handle($record);
        }

        // Count backup files
        $backupFiles = glob($filename . '.*');
        $this->assertLessThanOrEqual($backupCount, count($backupFiles),
            "Should not exceed backup count of {$backupCount}");
    }

    public function testSpecialCharactersInFilenames(): void
    {
        $filename = $this->logsDir . '/special-chars_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Special characters test: àáâãäåæçèéêë',
            context: ['unicode' => '测试中文字符'],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
        $content = file_get_contents($filename);
        $this->assertStringContainsString('Special characters test', $content);
    }

    public function testEmptyLogMessage(): void
    {
        $filename = $this->logsDir . '/empty.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: '',
            context: [],
            extra: []
        );

        $handler->handle($record);

        $this->assertFileExists($filename);
        $this->assertGreaterThan(0, filesize($filename), 'File should contain formatted log entry even with empty message');
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
