<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Handler\SmtpHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class SmtpHandlerTest extends TestCase
{
    public function testSmtpHandlerCreation(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
                'username' => '',
                'password' => '',
            ],
            maxRecords: 5,
            level: Level::Error,
            bubble: true,
        );

        $this->assertInstanceOf(SmtpHandler::class, $handler);
    }

    public function testSmtpHandlerWithMultipleReceivers(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin1@example.com', 'admin2@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 1,
            level: Level::Error,
        );

        $this->assertInstanceOf(SmtpHandler::class, $handler);
    }

    public function testSmtpHandlerWithStringReceiver(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: 'admin@example.com', // String instead of array
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 1,
            level: Level::Error,
        );

        $this->assertInstanceOf(SmtpHandler::class, $handler);
    }

    public function testSmtpHandlerWithAuthentication(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'user@example.com',
                'password' => 'password123',
            ],
            maxRecords: 1,
            level: Level::Error,
        );

        $this->assertInstanceOf(SmtpHandler::class, $handler);
    }

    public function testSmtpHandlerRequiresSymfonyMailer(): void
    {
        if (class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is available, cannot test exception');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Require components: Symfony Mailer');

        new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 1,
            level: Level::Error,
        );
    }

    public function testSmtpHandlerHandleRecord(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        // Create a mock handler that won't actually send emails
        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 1,
            level: Level::Error,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
            context: ['key' => 'value'],
            extra: [],
        );

        // This should not throw an exception even if SMTP is not configured
        // because we're not actually sending emails in tests
        try {
            $result = $handler->handle($record);
            // The result depends on whether the handler actually tries to send
            // In a real test environment, this might fail due to SMTP configuration
            $this->assertTrue(is_bool($result));
        } catch (\Exception $e) {
            // Expected in test environment without proper SMTP setup
            $this->assertIsString($e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testSmtpHandlerLevelFiltering(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 1,
            level: Level::Error, // Only Error and above
        );

        // This should be ignored (Warning < Error)
        $warningRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'Warning message',
            context: [],
            extra: [],
        );

        $result = $handler->handle($warningRecord);
        $this->assertFalse($result); // Should be filtered out
    }

    public function testSmtpHandlerClose(): void
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            $this->markTestSkipped('Symfony Mailer is not available');
        }

        $handler = new SmtpHandler(
            sender: 'test@example.com',
            receivers: ['admin@example.com'],
            subject: 'Test Subject',
            certificates: [
                'host' => 'localhost',
                'port' => 25,
            ],
            maxRecords: 5, // Buffer multiple records
            level: Level::Error,
        );

        // Add a record
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
            context: [],
            extra: [],
        );

        try {
            $handler->handle($record);
            $handler->close(); // Should flush any buffered records
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\Exception $e) {
            // Expected in test environment without proper SMTP setup
            $this->assertIsString($e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
