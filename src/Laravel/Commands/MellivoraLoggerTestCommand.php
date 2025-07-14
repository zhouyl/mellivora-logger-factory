<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel\Commands;

use Illuminate\Console\Command;
use Mellivora\Logger\Laravel\Facades\MLog;
use Monolog\Level;
use RuntimeException;
use Throwable;

/**
 * Mellivora Logger Test Command.
 *
 * Used to test various functionalities of Mellivora Logger
 */
class MellivoraLoggerTestCommand extends Command
{
    /**
     * Command signature.
     */
    protected $signature = 'mellivora:log-test
                            {--channel= : Specify log channel}
                            {--level=info : Specify log level}
                            {--message=Test message : Specify log message}';

    /**
     * Command description.
     */
    protected $description = 'Test Mellivora Logger functionality';

    /**
     * Execute command.
     */
    public function handle(): int
    {
        $channel = $this->option('channel');
        $level = $this->option('level');
        $message = $this->option('message');

        $this->info('Testing Mellivora Logger...');
        $this->newLine();

        // Test basic logging
        $this->testBasicLogging($channel, $level, $message);

        // Test all log levels
        $this->testAllLevels($channel);

        // Test exception logging
        $this->testExceptionLogging($channel);

        // Test multiple channels
        $this->testMultipleChannels();

        $this->newLine();
        $this->info('✅ All tests completed!');

        return self::SUCCESS;
    }

    /**
     * Test basic logging.
     */
    protected function testBasicLogging(?string $channel, string $level, string $message): void
    {
        $this->info('1. Testing basic logging...');

        try {
            if ($channel) {
                MellivoraLogger::logWith($channel, $level, $message, ['test' => 'basic']);
                $this->line("   ✓ Logged to channel '{$channel}' with level '{$level}'");
            } else {
                MellivoraLogger::log($level, $message, ['test' => 'basic']);
                $this->line("   ✓ Logged to default channel with level '{$level}'");
            }
        } catch (Throwable $e) {
            $this->error("   ✗ Failed: {$e->getMessage()}");
        }
    }

    /**
     * Test all log levels.
     */
    protected function testAllLevels(?string $channel): void
    {
        $this->info('2. Testing all log levels...');

        $levels = [
            'debug' => 'Debug message',
            'info' => 'Info message',
            'warning' => 'Warning message',
            'error' => 'Error message',
            'critical' => 'Critical message',
        ];

        foreach ($levels as $level => $message) {
            try {
                MellivoraLogger::$level($message, ['level_test' => true], $channel);
                $this->line("   ✓ {$level}: {$message}");
            } catch (Throwable $e) {
                $this->error("   ✗ {$level} failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Test exception logging.
     */
    protected function testExceptionLogging(?string $channel): void
    {
        $this->info('3. Testing exception logging...');

        try {
            $exception = new RuntimeException('Test exception for logging', 12345);
            MLog::exception($exception, Level::Error, $channel);
            $this->line('   ✓ Exception logged successfully');
        } catch (Throwable $e) {
            $this->error("   ✗ Exception logging failed: {$e->getMessage()}");
        }
    }

    /**
     * Test multiple channels.
     */
    protected function testMultipleChannels(): void
    {
        $this->info('4. Testing multiple channels...');

        $channels = ['app', 'api', 'queue', 'database', 'security'];

        foreach ($channels as $channel) {
            try {
                MLog::info(
                    "Test message for {$channel} channel",
                    ['channel_test' => true],
                    $channel,
                );
                $this->line("   ✓ {$channel} channel");
            } catch (Throwable $e) {
                $this->error("   ✗ {$channel} channel failed: {$e->getMessage()}");
            }
        }
    }
}
