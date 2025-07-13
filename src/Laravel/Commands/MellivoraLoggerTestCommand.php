<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel\Commands;

use Illuminate\Console\Command;
use Mellivora\Logger\Laravel\Facades\MLog;
use Monolog\Level;

/**
 * Mellivora Logger 测试命令.
 *
 * 用于测试 Mellivora Logger 的各种功能
 */
class MellivoraLoggerTestCommand extends Command
{
    /**
     * 命令签名.
     */
    protected $signature = 'mellivora:log-test
                            {--channel= : 指定日志通道}
                            {--level=info : 指定日志级别}
                            {--message=Test message : 指定日志消息}';

    /**
     * 命令描述.
     */
    protected $description = 'Test Mellivora Logger functionality';

    /**
     * 执行命令.
     */
    public function handle(): int
    {
        $channel = $this->option('channel');
        $level = $this->option('level');
        $message = $this->option('message');

        $this->info('Testing Mellivora Logger...');
        $this->newLine();

        // 测试基本日志记录
        $this->testBasicLogging($channel, $level, $message);

        // 测试所有日志级别
        $this->testAllLevels($channel);

        // 测试异常记录
        $this->testExceptionLogging($channel);

        // 测试多通道
        $this->testMultipleChannels();

        $this->newLine();
        $this->info('✅ All tests completed!');

        return self::SUCCESS;
    }

    /**
     * 测试基本日志记录.
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
        } catch (\Throwable $e) {
            $this->error("   ✗ Failed: {$e->getMessage()}");
        }
    }

    /**
     * 测试所有日志级别.
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
            } catch (\Throwable $e) {
                $this->error("   ✗ {$level} failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * 测试异常记录.
     */
    protected function testExceptionLogging(?string $channel): void
    {
        $this->info('3. Testing exception logging...');

        try {
            $exception = new \RuntimeException('Test exception for logging', 12345);
            MLog::exception($exception, Level::Error, $channel);
            $this->line('   ✓ Exception logged successfully');
        } catch (\Throwable $e) {
            $this->error("   ✗ Exception logging failed: {$e->getMessage()}");
        }
    }

    /**
     * 测试多通道.
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
            } catch (\Throwable $e) {
                $this->error("   ✗ {$channel} channel failed: {$e->getMessage()}");
            }
        }
    }
}
