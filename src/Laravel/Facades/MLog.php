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
     * Log DEBUG level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log INFO level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log WARNING level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log ERROR level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log CRITICAL level message.
     *
     * @param string $message Log message
     * @param array $context Context data
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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
     * Log exception.
     *
     * @param \Throwable $exception Exception object
     * @param Level|string $level Log level, defaults to Error
     * @param null|string $channel Log channel, uses default channel when null
     *
     * @return bool Whether the log was successfully recorded
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

        // Fall back to standard logging
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
     * Get Logger instance for specified channel.
     *
     * @param null|string $channel Log channel name
     *
     * @return LoggerInterface Logger instance
     */
    public static function channel(?string $channel = null): LoggerInterface
    {
        return static::getFacadeRoot()->get($channel);
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return LoggerFactory::class;
    }
}
