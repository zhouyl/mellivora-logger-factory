<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Mellivora\Logger\Logger;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Psr\Log\LoggerInterface;

/**
 * MLog Facade.
 *
 * Provides Laravel Facade-style logging access interface
 *
 * @method static LoggerInterface get(?string $channel = null)
 * @method static LoggerInterface make(string $channel, array|string|null $handlers = null)
 * @method static bool exists(string $channel)
 * @method static LoggerFactory add(string $channel, LoggerInterface $logger)
 * @method static LoggerFactory setDefault(string $default)
 * @method static string getDefault()
 * @method static LoggerFactory release()
 *
 * @see LoggerFactory
 */
class MLog extends Facade
{
    /**
     * Log a message to the default channel.
     *
     * @param Level|string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     *
     * @return bool Whether the log was successfully recorded
     */
    public static function log(string|Level $level, string $message, array $context = []): bool
    {
        return static::getFacadeRoot()->get()->log($level, $message, $context);
    }

    /**
     * Log message to specified channel.
     *
     * @param string $channel Log channel name
     * @param Level|string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     *
     * @return bool Whether the log was successfully recorded
     */
    public static function logWith(
        string $channel,
        string|Level $level,
        string $message,
        array $context = [],
    ): bool {
        return static::getFacadeRoot()->get($channel)->log($level, $message, $context);
    }

    /**
     * 记录 DEBUG 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function debug(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        return $logger->debug($message, $context);
    }

    /**
     * 记录 INFO 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function info(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        return $logger->info($message, $context);
    }

    /**
     * 记录 WARNING 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function warning(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        return $logger->warning($message, $context);
    }

    /**
     * 记录 ERROR 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function error(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        return $logger->error($message, $context);
    }

    /**
     * 记录 CRITICAL 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function critical(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        return $logger->critical($message, $context);
    }

    /**
     * 记录异常日志.
     *
     * @param \Throwable $exception 异常对象
     * @param Level|string $level 日志级别，默认为 Error
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    public static function exception(
        \Throwable $exception,
        string|Level $level = Level::Error,
        ?string $channel = null,
    ): bool {
        $logger = static::getFacadeRoot()->get($channel);

        if ($logger instanceof Logger) {
            return $logger->addException($exception, $level);
        }

        // 回退到标准日志记录
        $context = [
            'exception' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        return $logger->log($level, $exception->getMessage(), $context);
    }

    /**
     * 获取指定通道的 Logger 实例.
     *
     * @param null|string $channel 日志通道名称
     *
     * @return LoggerInterface Logger 实例
     */
    public static function channel(?string $channel = null): LoggerInterface
    {
        return static::getFacadeRoot()->get($channel);
    }

    /**
     * 获取组件的注册名称.
     */
    protected static function getFacadeAccessor(): string
    {
        return LoggerFactory::class;
    }
}
