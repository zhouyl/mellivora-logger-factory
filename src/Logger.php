<?php

declare(strict_types=1);

namespace Mellivora\Logger;

use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Throwable;
use UnhandledMatchError;
use ValueError;

/**
 * Logger class extension - Custom Logger implementation based on Monolog.
 *
 * Provides additional functionality:
 * - Global log level control
 * - Filter support
 * - Handler management methods
 * - Exception logging
 */
class Logger extends MonoLogger
{
    /**
     * Filter array for custom filtering before logging.
     *
     * @var array<callable(Level, string, array): bool>
     */
    protected array $filters = [];

    /**
     * Current Logger's log level, logs below this level will be ignored.
     */
    protected Level $level;

    /**
     * Constructor.
     *
     * @param string $name Logger name
     * @param array $handlers Handler array
     * @param array $processors Processor array
     * @param null|DateTimeZone $timezone Timezone setting
     */
    public function __construct(
        string        $name,
        array         $handlers = [],
        array         $processors = [],
        ?DateTimeZone $timezone = null,
    ) {
        parent::__construct($name, $handlers, $processors, $timezone);
        $this->level = Level::Debug;
    }

    /**
     * Convert Logger to string representation.
     *
     * @return string String representation of Logger
     */
    public function __toString(): string
    {
        return "Logger({$this->getName()})";
    }

    /**
     * Set the current Logger's log level.
     *
     * Logs below this level will not be passed to Handler for processing
     *
     * @param int|Level|string $level Log level, supports Level enum, level name string or level numeric value
     *
     * @throws InvalidArgumentException When level type is invalid
     *
     * @return self Returns current instance to support method chaining
     */
    public function setLevel(mixed $level): self
    {
        $this->level = match (true) {
            $level instanceof Level => $level,
            is_string($level) => $this->parseStringLevel($level),
            is_int($level) => Level::from($level),
            default => throw new InvalidArgumentException('Invalid level type'),
        };

        return $this;
    }

    /**
     * Get the current Logger's log level.
     *
     * @return Level Currently set log level
     */
    public function getLevel(): Level
    {
        return $this->level;
    }

    /**
     * Get Handler instance by class name.
     *
     * If there are multiple Handlers of the same type, the first matching instance will be returned
     *
     * @param string $class Handler class name (supports full class name or class name)
     *
     * @return mixed Matching Handler instance, returns false if not found
     *
     * @example $logger->getHandler(FileHandler::class)
     */
    public function getHandler(string $class): mixed
    {
        foreach ($this->handlers as $handler) {
            if (is_a($handler, $class)) {
                return $handler;
            }
        }

        return false;
    }

    /**
     * Remove Handler by class name.
     *
     * If there are multiple Handlers of the same type, all will be removed
     *
     * @param string $class Handler class name
     *
     * @return bool Returns true if successfully removed, false if not found
     *
     * @example $logger->removeHandler(FileHandler::class)
     */
    public function removeHandler(string $class): bool
    {
        $removed = false;
        foreach ($this->handlers as $key => $handler) {
            if (is_a($handler, $class)) {
                unset($this->handlers[$key]);
                $this->handlers = array_values($this->handlers); // Re-index
                $removed = true;
                break; // Only remove the first match
            }
        }

        return $removed;
    }

    /**
     * Add a filter.
     *
     * Filter is a callable object used for custom filtering before logging.
     * Filter receives three parameters: Level $level, string $message, array $context
     * Returns true to allow logging, false to reject logging.
     *
     * @param callable(Level, string, array): bool $callback Filter callback function
     *
     * @throws InvalidArgumentException When callback is not callable
     *
     * @return self Returns current instance to support method chaining
     */
    public function pushFilter(mixed $callback): self
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException(
                'Filters must be valid callables (callback or object with an ' .
                '__invoke method), ' . var_export($callback, true) . ' given',
            );
        }

        array_unshift($this->filters, $callback);

        return $this;
    }

    /**
     * Pop and get the first registered filter.
     *
     * @throws LogicException When filter stack is empty
     *
     * @return callable Filter callback function
     */
    public function popFilter(): callable
    {
        if ($this->filters === []) {
            throw new LogicException('You tried to pop from an empty filter stack.');
        }

        return array_shift($this->filters);
    }

    /**
     * Get all registered filters.
     *
     * @return array<callable(Level, string, array): bool> Filter array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Add log record.
     *
     * Override parent method to support custom level control and filter functionality
     *
     * @param int|Level $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @param null|DateTimeInterface $datetime Log time
     *
     * @return bool Whether log was successfully recorded
     */
    public function addRecord(
        int|Level $level,
        string $message,
        array $context = [],
        ?DateTimeInterface $datetime = null,
    ): bool {
        $levelObj = $level instanceof Level ? $level : Level::fromValue($level);

        if ($levelObj->value < $this->level->value) {
            return false;
        }

        // apply filters
        foreach ($this->filters as $filter) {
            if (!$filter($levelObj, $message, $context)) {
                return false;
            }
        }

        return parent::addRecord($levelObj, $message, $context, $datetime);
    }

    /**
     * Record exception log.
     *
     * Automatically extract exception details (class name, error code, message, file, line number) and record to log
     *
     * @param Throwable $ex Exception object
     * @param int|Level|string $level Log level, defaults to Error
     *
     * @return bool Whether log was successfully recorded
     */
    public function addException(Throwable $ex, mixed $level = Level::Error): bool
    {
        // Convert string level to Level enum if needed
        $levelEnum = match (true) {
            $level instanceof Level => $level,
            is_string($level) => $this->parseStringLevel($level),
            is_int($level) => Level::from($level),
            default => Level::Error,
        };

        return $this->addRecord(
            $levelEnum,
            $ex->getMessage(),
            [
                'exception' => get_class($ex),
                'code' => $ex->getCode(),
                'message' => $ex->getMessage(),
                'file' => $ex->getFile(),
                'line' => $ex->getLine(),
                'trace' => $ex->getTraceAsString(),
            ],
        );
    }

    /**
     * Parse string level.
     */
    private function parseStringLevel(string $level): Level
    {
        try {
            return Level::fromName($level);
        } catch (UnhandledMatchError|ValueError $e) {
            throw new InvalidArgumentException("Invalid level string: {$level}", 0, $e);
        }
    }
}
