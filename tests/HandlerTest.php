<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_logger_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testNamedRotatingFileHandler(): void
    {
        $filename = $this->tempDir . '/test.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Test that handler can be created without errors
        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        // Test that handle method works without throwing exceptions
        $handler->handle($record);

        $handler->close();
    }

    public function testNamedRotatingFileHandlerWithRelativePath(): void
    {
        $handler = new NamedRotatingFileHandler(
            filename: 'logs/relative.log',
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Test that handler can be created with relative path
        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'relative',
            level: Level::Info,
            message: 'Relative path test',
            context: [],
            extra: [],
        );

        $handler->handle($record);

        $handler->close();
    }

    public function testNamedRotatingFileHandlerBuffering(): void
    {
        $filename = $this->tempDir . '/buffered.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 2, // Buffer 2 records
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Test that handler can be created with buffering
        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);

        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'buffered',
            level: Level::Info,
            message: 'First message',
            context: [],
            extra: [],
        );

        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'buffered',
            level: Level::Info,
            message: 'Second message',
            context: [],
            extra: [],
        );

        // Test that both records can be handled
        $handler->handle($record1);
        $handler->handle($record2);

        $handler->close();
    }

    public function testSmtpHandlerRequiresSymfonyMailer(): void
    {
        // Test that SmtpHandler requires Symfony Mailer
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Require components: Symfony Mailer');

            new \Mellivora\Logger\Handler\SmtpHandler(
                sender: 'test@example.com',
                receivers: ['admin@example.com'],
                subject: 'Test',
                certificates: [],
                maxRecords: 1,
                level: Level::Error,
            );
        } else {
            $this->markTestSkipped('Symfony Mailer is available, cannot test exception');
        }
    }

    public function testNamedRotatingFileHandlerWithDifferentParameters(): void
    {
        $filename = $this->tempDir . '/param_test.log';

        // Test with different parameters
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 2048,
            backupCount: 5,
            bufferSize: 10,
            dateFormat: 'Y-m-d-H',
            level: Level::Warning,
            bubble: false,
            filePermission: 0o644,
            useLocking: true,
        );

        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'param_test',
            level: Level::Error,
            message: 'Error message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testNamedRotatingFileHandlerFilenameGeneration(): void
    {
        // Test filename generation with channel placeholder
        $filename = $this->tempDir . '/logs/%channel%.log';

        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'channel_test',
            level: Level::Info,
            message: 'Channel test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testNamedRotatingFileHandlerLevelFiltering(): void
    {
        $filename = $this->tempDir . '/level_test.log';

        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Warning, // Only Warning and above
        );

        // This should be handled (Error >= Warning)
        $errorRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'level_test',
            level: Level::Error,
            message: 'Error message',
            context: [],
            extra: [],
        );

        // This should be ignored (Info < Warning)
        $infoRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'level_test',
            level: Level::Info,
            message: 'Info message',
            context: [],
            extra: [],
        );

        $handler->handle($errorRecord);
        $handler->handle($infoRecord);

        // Just test that no exceptions are thrown
        $this->assertTrue(true);

        $handler->close();
    }

    public function testNamedRotatingFileHandlerBubbling(): void
    {
        $filename = $this->tempDir . '/bubble_test.log';

        // Test with bubble = false
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
            bubble: false,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'bubble_test',
            level: Level::Info,
            message: 'Bubble test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        // Just test that no exceptions are thrown
        $this->assertTrue(true);

        $handler->close();
    }

    public function testNamedRotatingFileHandlerFlush(): void
    {
        $filename = $this->tempDir . '/flush_test.log';

        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 5, // Buffer 5 records
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Add multiple records
        for ($i = 1; $i <= 3; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'flush_test',
                level: Level::Info,
                message: "Message $i",
                context: [],
                extra: [],
            );
            $handler->handle($record);
        }

        // Manually flush
        $handler->flush();
        $handler->close();
    }

    public function testNamedRotatingFileHandlerReset(): void
    {
        $filename = $this->tempDir . '/reset_test.log';

        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 5,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'reset_test',
            level: Level::Info,
            message: 'Reset test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);

        // Reset the handler
        $handler->reset();
        $handler->close();
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
