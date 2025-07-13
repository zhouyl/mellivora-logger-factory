<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class NamedRotatingFileHandlerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_handler_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testHandlerCreation(): void
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

        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);
    }

    public function testHandlerWithAllParameters(): void
    {
        $filename = $this->tempDir . '/full_test.log';
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
    }

    public function testHandlerWithChannelPlaceholder(): void
    {
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
            channel: 'test_channel',
            level: Level::Info,
            message: 'Test message with channel placeholder',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testHandlerWithRelativePath(): void
    {
        $handler = new NamedRotatingFileHandler(
            filename: 'logs/relative.log',
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

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

    public function testHandlerBuffering(): void
    {
        $filename = $this->tempDir . '/buffered.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 3, // Buffer 3 records
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Add multiple records
        for ($i = 1; $i <= 5; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'buffered',
                level: Level::Info,
                message: "Buffered message $i",
                context: [],
                extra: [],
            );
            $handler->handle($record);
        }

        $handler->close();
    }

    public function testHandlerLevelFiltering(): void
    {
        $filename = $this->tempDir . '/level_filter.log';
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
            channel: 'level_filter',
            level: Level::Error,
            message: 'Error message',
            context: [],
            extra: [],
        );

        // This should be ignored (Info < Warning)
        $infoRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'level_filter',
            level: Level::Info,
            message: 'Info message',
            context: [],
            extra: [],
        );

        $handler->handle($errorRecord);
        $handler->handle($infoRecord);
        $handler->close();
    }

    public function testHandlerBubbling(): void
    {
        $filename = $this->tempDir . '/bubble.log';

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
            channel: 'bubble',
            level: Level::Info,
            message: 'Bubble test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testHandlerFlush(): void
    {
        $filename = $this->tempDir . '/flush.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 5, // Buffer 5 records
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Add some records
        for ($i = 1; $i <= 3; $i++) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'flush',
                level: Level::Info,
                message: "Flush test message $i",
                context: [],
                extra: [],
            );
            $handler->handle($record);
        }

        // Manually flush
        $handler->flush();
        $handler->close();
    }

    public function testHandlerReset(): void
    {
        $filename = $this->tempDir . '/reset.log';
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
            channel: 'reset',
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

    public function testHandlerWithFormatter(): void
    {
        $filename = $this->tempDir . '/formatted.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Set a formatter
        $formatter = new \Monolog\Formatter\LineFormatter(
            "[%datetime%] %level_name%: %message%\n",
            'Y-m-d H:i:s',
        );
        $handler->setFormatter($formatter);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'formatted',
            level: Level::Info,
            message: 'Formatted message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testHandlerWithProcessor(): void
    {
        $filename = $this->tempDir . '/processed.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        // Add a processor
        $handler->pushProcessor(function ($record) {
            $record->extra['test_processor'] = 'processed';
            return $record;
        });

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'processed',
            level: Level::Info,
            message: 'Processed message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testHandlerWithZeroMaxBytes(): void
    {
        $filename = $this->tempDir . '/no_rotation.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 0, // No rotation
            backupCount: 3,
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'no_rotation',
            level: Level::Info,
            message: 'No rotation test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->close();
    }

    public function testHandlerWithZeroBackupCount(): void
    {
        $filename = $this->tempDir . '/no_backup.log';
        $handler = new NamedRotatingFileHandler(
            filename: $filename,
            maxBytes: 1024,
            backupCount: 0, // No backup files
            bufferSize: 0,
            dateFormat: 'Y-m-d',
            level: Level::Debug,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'no_backup',
            level: Level::Info,
            message: 'No backup test message',
            context: [],
            extra: [],
        );

        $handler->handle($record);
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
