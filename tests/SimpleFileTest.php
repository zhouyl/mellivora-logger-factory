<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\NamedRotatingFileHandler;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Simple file operations test to debug issues.
 */
class SimpleFileTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_simple_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        LoggerFactory::setRootPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testBasicHandlerCreation(): void
    {
        $filename = $this->tempDir . '/test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 1);

        $this->assertInstanceOf(NamedRotatingFileHandler::class, $handler);
    }

    public function testGetFilename(): void
    {
        $filename = $this->tempDir . '/test_%date%_%channel%.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 1);

        $actualFilename = $handler->getFilename('test');
        $this->assertStringContainsString('test', $actualFilename);
        $this->assertStringContainsString(date('Y-m-d'), $actualFilename);
    }

    public function testHandlerWithLoggerFactory(): void
    {
        $config = [
            'handlers' => [
                'file' => [
                    'class' => NamedRotatingFileHandler::class,
                    'params' => [
                        'filename' => $this->tempDir . '/factory_test.log',
                        'maxBytes' => 1024,
                        'backupCount' => 3,
                        'bufferSize' => 1,
                    ],
                ],
            ],
            'loggers' => [
                'test' => ['file'],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->get('test');

        $this->assertNotNull($logger);

        // Test logging
        $logger->info('Factory test message');
        $this->assertTrue(true); // If we get here without exception, test passes
    }

    public function testDirectFileWrite(): void
    {
        // Test direct file writing to ensure filesystem works
        $filename = $this->tempDir . '/direct_test.log';
        file_put_contents($filename, 'Direct write test');

        $this->assertFileExists($filename);
        $this->assertEquals('Direct write test', file_get_contents($filename));
    }

    public function testHandlerIsHandling(): void
    {
        $filename = $this->tempDir . '/handling_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 1, 'Y-m-d', Level::Info);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $this->assertTrue($handler->isHandling($record));

        $debugRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'Debug message',
            context: [],
            extra: [],
        );

        $this->assertFalse($handler->isHandling($debugRecord));
    }

    public function testHandlerWithBuffer(): void
    {
        $filename = $this->tempDir . '/buffer_test.log';
        $handler = new NamedRotatingFileHandler($filename, 1024, 3, 2); // Buffer size 2

        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'First message',
            context: [],
            extra: [],
        );

        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Second message',
            context: [],
            extra: [],
        );

        // Handle records
        $handler->handle($record1);
        $handler->handle($record2);

        // Force flush
        $handler->flush();

        // Check if file was created
        $actualFilename = $handler->getFilename('test');
        $this->assertFileExists($actualFilename);
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
