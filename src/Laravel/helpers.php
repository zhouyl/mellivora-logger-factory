<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Mellivora\Logger\LoggerFactory;
use Monolog\Level;
use Psr\Log\LoggerInterface;

if (!function_exists('mlog')) {
    /**
     * Log a message to the default channel.
     *
     * @param Level|string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     *
     * @return bool Whether the log was successfully recorded
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
            // Silent failure to avoid log recording errors affecting the application
            return false;
        }
    }
}

if (!function_exists('mlog_with')) {
    /**
     * Log a message to the specified channel.
     *
     * @param string $channel Log channel name
     * @param Level|string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     *
     * @return bool Whether the log was successfully recorded
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
        } catch (Throwable) {
            // Silent failure to avoid log recording errors affecting the application
            return false;
        }
    }
}

if (!function_exists('mlogger')) {
    /**
     * Get a Logger instance for the specified channel.
     *
     * @param null|string $channel Log channel name, returns default channel when null
     *
     * @return LoggerInterface Logger instance
     */
    function mlogger(?string $channel = null): LoggerInterface
    {
        $factory = App::make(LoggerFactory::class);

        return $factory->get($channel);
    }
}

if (!function_exists('mlog_debug')) {
    /**
     * Log a DEBUG level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log an INFO level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log a WARNING level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log an ERROR level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log a CRITICAL level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log an exception.
     *
     * @param Throwable $exception Exception object
     * @param Level|string $level Log level, defaults to Error
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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

            // Fall back to standard logging
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
        } catch (Throwable) {
            return false;
        }
    }
}
