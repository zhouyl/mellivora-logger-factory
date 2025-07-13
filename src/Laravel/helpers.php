<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Psr\Log\LoggerInterface;

if (!function_exists('mlog')) {
    /**
     * 记录日志到默认通道.
     *
     * @param Level|string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     *
     * @return bool 是否成功记录
     */
    function mlog(string|Level $level, string $message, array $context = []): bool
    {
        try {
            $logger = App::make('mellivora.logger');

            if ($level instanceof Level) {
                return $logger->log($level, $message, $context);
            }

            return $logger->log($level, $message, $context);
        } catch (Throwable $e) {
            // 静默失败，避免日志记录错误影响应用程序
            return false;
        }
    }
}

if (!function_exists('mlog_with')) {
    /**
     * 记录日志到指定通道.
     *
     * @param string $channel 日志通道名称
     * @param Level|string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     *
     * @return bool 是否成功记录
     */
    function mlog_with(
        string $channel,
        string|Level $level,
        string $message,
        array $context = [],
    ): bool {
        try {
            $factory = App::make(LoggerFactory::class);
            $logger = $factory->get($channel);

            if ($level instanceof Level) {
                return $logger->log($level, $message, $context);
            }

            return $logger->log($level, $message, $context);
        } catch (Throwable $e) {
            // 静默失败，避免日志记录错误影响应用程序
            return false;
        }
    }
}

if (!function_exists('mlogger')) {
    /**
     * 获取指定通道的 Logger 实例.
     *
     * @param null|string $channel 日志通道名称，为 null 时返回默认通道
     *
     * @return LoggerInterface Logger 实例
     */
    function mlogger(?string $channel = null): LoggerInterface
    {
        $factory = App::make(LoggerFactory::class);

        return $factory->get($channel);
    }
}

if (!function_exists('mlog_debug')) {
    /**
     * 记录 DEBUG 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_debug(string $message, array $context = [], ?string $channel = null): bool
    {
        return $channel
            ? mlog_with($channel, Level::Debug, $message, $context)
            : mlog(Level::Debug, $message, $context);
    }
}

if (!function_exists('mlog_info')) {
    /**
     * 记录 INFO 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_info(string $message, array $context = [], ?string $channel = null): bool
    {
        return $channel
            ? mlog_with($channel, Level::Info, $message, $context)
            : mlog(Level::Info, $message, $context);
    }
}

if (!function_exists('mlog_warning')) {
    /**
     * 记录 WARNING 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_warning(string $message, array $context = [], ?string $channel = null): bool
    {
        return $channel
            ? mlog_with($channel, Level::Warning, $message, $context)
            : mlog(Level::Warning, $message, $context);
    }
}

if (!function_exists('mlog_error')) {
    /**
     * 记录 ERROR 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_error(string $message, array $context = [], ?string $channel = null): bool
    {
        return $channel
            ? mlog_with($channel, Level::Error, $message, $context)
            : mlog(Level::Error, $message, $context);
    }
}

if (!function_exists('mlog_critical')) {
    /**
     * 记录 CRITICAL 级别日志.
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_critical(
        string $message,
        array $context = [],
        ?string $channel = null,
    ): bool {
        return $channel
            ? mlog_with($channel, Level::Critical, $message, $context)
            : mlog(Level::Critical, $message, $context);
    }
}

if (!function_exists('mlog_exception')) {
    /**
     * 记录异常日志.
     *
     * @param Throwable $exception 异常对象
     * @param Level|string $level 日志级别，默认为 Error
     * @param null|string $channel 日志通道，为 null 时使用默认通道
     *
     * @return bool 是否成功记录
     */
    function mlog_exception(
        Throwable $exception,
        string|Level $level = Level::Error,
        ?string $channel = null,
    ): bool {
        try {
            $logger = $channel ? mlogger($channel) : mlogger();

            if ($logger instanceof Mellivora\Logger\Logger) {
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

            if ($level instanceof Level) {
                return $logger->log($level, $exception->getMessage(), $context);
            }

            return $logger->log($level, $exception->getMessage(), $context);
        } catch (Throwable $e) {
            return false;
        }
    }
}
