<?php

declare(strict_types=1);

namespace Mellivora\Logger;

use ArrayAccess;
use InvalidArgumentException;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use UnhandledMatchError;
use ValueError;

/**
 * Logger Factory Class - Manages project logging through parameter configuration.
 *
 * Provides configuration-based log management functionality, supporting various combinations
 * of Handlers, Formatters, and Processors. Implements ArrayAccess interface for array-like
 * access to Logger instances.
 */
class LoggerFactory implements ArrayAccess
{
    /**
     * Project root directory path, used to assist log file location.
     */
    protected static ?string $rootPath = null;

    /**
     * Default Logger channel name.
     */
    protected ?string $default = null;

    /**
     * Formatter configuration array.
     *
     * Format: ['formatter_name' => ['class' => 'ClassName', 'params' => [...]]]
     *
     * @var array<string, array{class: string, params?: array}>
     */
    protected array $formatters = [];

    /**
     * Processor configuration array.
     *
     * Format: ['processor_name' => ['class' => 'ClassName', 'params' => [...]]]
     *
     * @var array<string, array{class: string, params?: array}>
     */
    protected array $processors = [];

    /**
     * Handler configuration array.
     *
     * Format: ['handler_name' => [
     *     'class' => 'ClassName',
     *     'params' => [...],
     *     'formatter' => 'formatter_name',
     *     'processors' => [...]
     * ]]
     *
     * @var array<string, array{
     *     class: string,
     *     params?: array,
     *     formatter?: string,
     *     processors?: array
     * }>
     */
    protected array $handlers = [];

    /**
     * Logger channel configuration array.
     *
     * Format: ['logger_name' => ['handler1', 'handler2', ...]]
     *
     * @var array<string, array<string>>
     */
    protected array $loggers = [];

    /**
     * Cache of instantiated Logger instances.
     *
     * @var array<string, LoggerInterface>
     */
    protected array $instances = [];

    /**
     * Constructor.
     *
     * @param array $config Log configuration array, supports the following keys:
     *                      - formatters: Formatter configuration
     *                      - processors: Processor configuration
     *                      - handlers: Handler configuration
     *                      - loggers: Logger channel configuration
     *                      - default: Default Logger channel name
     */
    public function __construct(array $config = [])
    {
        $this->formatters = $config['formatters'] ?? [];
        $this->processors = $config['processors'] ?? [];
        $this->handlers = $config['handlers'] ?? [];
        $this->loggers = $config['loggers'] ?? [];

        if (isset($config['default'])) {
            $this->setDefault($config['default']);
        }
    }

    /**
     * Set project root directory.
     *
     * @param string $path Project root directory path
     *
     * @throws InvalidArgumentException When path is invalid
     */
    public static function setRootPath(string $path): void
    {
        // Allow setting non-existent paths for testing purposes
        if (is_dir($path)) {
            self::$rootPath = realpath($path);
        } else {
            self::$rootPath = $path;
        }
    }

    /**
     * Get project root directory.
     *
     * If root directory is not set, will automatically try to find path containing vendor directory as root
     *
     * @return null|string Project root directory path, returns null if not found
     */
    public static function getRootPath(): ?string
    {
        if (self::$rootPath === null) {
            foreach (['.', '../../..'] as $relativePath) {
                $path = realpath(dirname(__DIR__) . '/' . $relativePath);
                if ($path && is_dir($path) && is_dir($path . '/vendor')) {
                    self::setRootPath($path);
                    break;
                }
            }
        }

        return self::$rootPath;
    }

    /**
     * Create LoggerFactory instance from configuration array.
     *
     * @param array $config Log configuration array, containing formatters, processors, handlers, loggers etc.
     *
     * @return self LoggerFactory instance
     */
    public static function build(array $config): self
    {
        return new self($config);
    }

    /**
     * Create LoggerFactory instance from PHP configuration file.
     *
     * @param string $configFile PHP configuration file path, must be .php file
     *
     * @throws InvalidArgumentException When config file doesn't exist, format is incorrect or content is invalid
     *
     * @return self LoggerFactory instance
     */
    public static function buildWith(string $configFile): self
    {
        if (!file_exists($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        if (!str_ends_with($configFile, '.php')) {
            throw new InvalidArgumentException(
                "Only PHP configuration files are supported: {$configFile}",
            );
        }

        $config = require $configFile;

        if (!is_array($config)) {
            throw new InvalidArgumentException(
                "Configuration file must return an array: {$configFile}",
            );
        }

        return self::build($config);
    }

    /**
     * Set default Logger channel name.
     *
     * @param string $default Logger channel name, must be defined in configuration
     *
     * @throws RuntimeException When specified Logger channel is not defined
     *
     * @return self Returns current instance to support method chaining
     */
    public function setDefault(string $default): self
    {
        if (!isset($this->loggers[$default])) {
            throw new RuntimeException("Call to undefined logger channel '$default'");
        }

        $this->default = $default;

        return $this;
    }

    /**
     * Get default Logger channel name.
     *
     * If default channel is not set, will automatically select the first configured Logger channel,
     * if no Logger channels are configured, returns 'default'
     *
     * @return string Default Logger channel name
     */
    public function getDefault(): string
    {
        if ($this->default === null) {
            $this->default = $this->loggers !== []
                ? array_key_first($this->loggers)
                : 'default';
        }

        return $this->default;
    }

    /**
     * Register a Logger instance.
     *
     * @param string $channel Logger channel name
     * @param LoggerInterface $logger Logger instance
     *
     * @return self Returns current instance to support method chaining
     */
    public function add(string $channel, LoggerInterface $logger): self
    {
        $this->instances[$channel] = $logger;

        return $this;
    }

    /**
     * Get Logger instance by channel name.
     *
     * If instance doesn't exist, will automatically create based on configuration. If specified channel doesn't exist, will fall back to default channel.
     *
     * @param null|string $channel Logger channel name, uses default channel when null
     *
     * @return LoggerInterface Logger instance
     */
    public function get(?string $channel = null): LoggerInterface
    {
        if ($channel === null || $channel === '') {
            $channel = $this->getDefault();
        }

        if (!isset($this->instances[$channel])) {
            if (!isset($this->loggers[$channel])) {
                $channel = $this->getDefault();
            }

            $this->instances[$channel] = $this->make(
                $channel,
                $this->loggers[$channel] ?? null,
            );
        }

        return $this->instances[$channel];
    }

    /**
     * Create Logger instance based on configuration.
     *
     * @param string $channel Logger channel name
     * @param null|array<string>|string $handlers Handler name array or single Handler name
     *
     * @return Logger Configured Logger instance
     */
    public function make(string $channel, array|string|null $handlers = null): Logger
    {
        $logger = new Logger($channel);

        if ($handlers === null || $handlers === [] || $handlers === '') {
            return $logger->pushHandler(new NullHandler());
        }

        $handlerNames = is_array($handlers) ? $handlers : [$handlers];

        foreach ($handlerNames as $handlerName) {
            if (!isset($this->handlers[$handlerName])) {
                continue;
            }

            $option = $this->handlers[$handlerName];
            $handler = $this->newInstanceWithOption($option);

            // Add Processors
            if (isset($option['processors']) && is_array($option['processors'])) {
                foreach ($option['processors'] as $processorName) {
                    if (isset($this->processors[$processorName])) {
                        $handler->pushProcessor(
                            $this->newInstanceWithOption($this->processors[$processorName]),
                        );
                    }
                }
            }

            // Set Formatter
            if (isset($option['formatter'], $this->formatters[$option['formatter']])) {
                $handler->setFormatter(
                    $this->newInstanceWithOption($this->formatters[$option['formatter']]),
                );
            }

            $logger->pushHandler($handler);
        }

        return $logger;
    }

    /**
     * Check if specified Logger channel exists.
     *
     * @param string $channel Logger channel name
     *
     * @return bool Returns true if exists, false otherwise
     */
    public function exists(string $channel): bool
    {
        return isset($this->loggers[$channel]) || isset($this->instances[$channel]);
    }

    /**
     * Release all instantiated Loggers, clear instance cache.
     *
     * @return self Returns current instance to support method chaining
     */
    public function release(): self
    {
        $this->instances = [];

        return $this;
    }

    /**
     * ArrayAccess interface implementation: Set Logger instance.
     *
     * @param mixed $channel Logger channel name
     * @param mixed $value Logger instance
     */
    public function offsetSet(mixed $channel, mixed $value): void
    {
        $this->add($channel, $value);
    }

    /**
     * ArrayAccess interface implementation: Get Logger instance.
     *
     * @param mixed $channel Logger channel name
     *
     * @return mixed Logger instance
     */
    public function offsetGet(mixed $channel): mixed
    {
        return $this->get($channel);
    }

    /**
     * ArrayAccess interface implementation: Check if Logger exists.
     *
     * @param mixed $channel Logger channel name
     *
     * @return bool Returns true if exists, false otherwise
     */
    public function offsetExists(mixed $channel): bool
    {
        return $this->exists($channel);
    }

    /**
     * ArrayAccess interface implementation: Delete Logger (this operation is prohibited).
     *
     * @param mixed $channel Logger channel name
     */
    public function offsetUnset(mixed $channel): void
    {
        // This operation is prohibited, no action is performed
    }

    /**
     * Create class instance based on configuration options.
     *
     * Supports dynamic instance creation through reflection, automatically handles constructor parameter mapping.
     *
     * @param array{class: string, params?: array} $option Configuration options array
     *                                                     - class: Full class name (including namespace)
     *                                                     - params: Constructor parameter array, supports passing by name or position
     *
     * @throws InvalidArgumentException When class parameter is missing
     * @throws RuntimeException When class does not exist
     *
     * @return object Created class instance
     *
     * @example
     * ```php
     * $logger = $this->newInstanceWithOption([
     *     'class' => '\Mellivora\Logger\Logger',
     *     'params' => ['name' => 'myname'],
     * ]);
     * // Equivalent to: new \Mellivora\Logger\Logger('myname');
     * ```
     */
    protected function newInstanceWithOption(array $option): object
    {
        if (empty($option['class'])) {
            throw new InvalidArgumentException("Missing the 'class' parameter");
        }

        $class = $option['class'];
        if (!class_exists($class)) {
            throw new RuntimeException("Class '$class' not found");
        }

        if (empty($option['params'])) {
            return new $class();
        }

        $ref = new ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = $option['params'];
        $constructorParams = $constructor->getParameters();
        $args = [];

        // Fill parameters according to constructor parameter order
        foreach ($constructorParams as $index => $param) {
            $paramName = $param->getName();
            $value = null;

            if (isset($params[$paramName])) {
                $value = $params[$paramName];
            } elseif (isset($params[$index])) {
                $value = $params[$index];
            } elseif ($param->isDefaultValueAvailable()) {
                $value = $param->getDefaultValue();
            }

            // Convert string level to Level enum
            if ($paramName === 'level' && is_string($value)) {
                try {
                    $value = Level::fromName($value);
                } catch (PsrInvalidArgumentException|ValueError|UnhandledMatchError) {
                    // If conversion fails, keep original value
                }
            }

            $args[] = $value;
        }

        return $ref->newInstanceArgs($args);
    }
}
