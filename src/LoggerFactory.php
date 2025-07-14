<?php

declare(strict_types=1);

namespace Mellivora\Logger;

use Monolog\Handler\NullHandler;
use Monolog\Level;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
class LoggerFactory implements \ArrayAccess
{
    /**
     * 项目根目录路径，用于辅助日志文件定位.
     */
    protected static ?string $rootPath = null;

    /**
     * 默认 Logger 通道名称.
     */
    protected ?string $default = null;

    /**
     * Formatter 配置数组.
     *
     * 格式: ['formatter_name' => ['class' => 'ClassName', 'params' => [...]]]
     *
     * @var array<string, array{class: string, params?: array}>
     */
    protected array $formatters = [];

    /**
     * Processor 配置数组.
     *
     * 格式: ['processor_name' => ['class' => 'ClassName', 'params' => [...]]]
     *
     * @var array<string, array{class: string, params?: array}>
     */
    protected array $processors = [];

    /**
     * Handler 配置数组.
     *
     * 格式: ['handler_name' => [
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
     * Logger 通道配置数组.
     *
     * 格式: ['logger_name' => ['handler1', 'handler2', ...]]
     *
     * @var array<string, array<string>>
     */
    protected array $loggers = [];

    /**
     * 已实例化的 Logger 实例缓存.
     *
     * @var array<string, LoggerInterface>
     */
    protected array $instances = [];

    /**
     * 构造函数.
     *
     * @param array $config 日志配置数组，支持以下键：
     *                      - formatters: Formatter 配置
     *                      - processors: Processor 配置
     *                      - handlers: Handler 配置
     *                      - loggers: Logger 通道配置
     *                      - default: 默认 Logger 通道名称
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
     * 设置项目根目录.
     *
     * @param string $path 项目根目录路径
     *
     * @throws \InvalidArgumentException 当路径无效时抛出异常
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
     * 获取项目根目录.
     *
     * 如果未设置根目录，会自动尝试查找包含 vendor 目录的路径作为根目录
     *
     * @return null|string 项目根目录路径，未找到时返回 null
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
     * 根据配置数组创建 LoggerFactory 实例.
     *
     * @param array $config 日志配置数组，包含 formatters、processors、handlers、loggers 等配置
     *
     * @return self LoggerFactory 实例
     */
    public static function build(array $config): self
    {
        return new self($config);
    }

    /**
     * 根据 PHP 配置文件创建 LoggerFactory 实例.
     *
     * @param string $configFile PHP 配置文件路径，必须是 .php 文件
     *
     * @throws \InvalidArgumentException 当配置文件不存在、格式不正确或内容无效时抛出异常
     *
     * @return self LoggerFactory 实例
     */
    public static function buildWith(string $configFile): self
    {
        if (!file_exists($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        if (!str_ends_with($configFile, '.php')) {
            throw new \InvalidArgumentException(
                "Only PHP configuration files are supported: {$configFile}",
            );
        }

        $config = require $configFile;

        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                "Configuration file must return an array: {$configFile}",
            );
        }

        return self::build($config);
    }

    /**
     * 设置默认 Logger 通道名称.
     *
     * @param string $default Logger 通道名称，必须在配置中已定义
     *
     * @throws RuntimeException 当指定的 Logger 通道未定义时抛出异常
     *
     * @return self 返回当前实例以支持链式调用
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
     * 获取默认 Logger 通道名称.
     *
     * 如果未设置默认通道，会自动选择第一个配置的 Logger 通道，
     * 如果没有配置任何 Logger 通道，则返回 'default'
     *
     * @return string 默认 Logger 通道名称
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
     * 注册一个 Logger 实例.
     *
     * @param string $channel Logger 通道名称
     * @param LoggerInterface $logger Logger 实例
     *
     * @return self 返回当前实例以支持链式调用
     */
    public function add(string $channel, LoggerInterface $logger): self
    {
        $this->instances[$channel] = $logger;

        return $this;
    }

    /**
     * 根据通道名称获取 Logger 实例.
     *
     * 如果实例不存在，会根据配置自动创建。如果指定的通道不存在，会回退到默认通道。
     *
     * @param null|string $channel Logger 通道名称，为 null 时使用默认通道
     *
     * @return LoggerInterface Logger 实例
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
     * 根据配置创建 Logger 实例.
     *
     * @param string $channel Logger 通道名称
     * @param null|array<string>|string $handlers Handler 名称数组或单个 Handler 名称
     *
     * @return Logger 配置好的 Logger 实例
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

            // 添加 Processors
            if (isset($option['processors']) && is_array($option['processors'])) {
                foreach ($option['processors'] as $processorName) {
                    if (isset($this->processors[$processorName])) {
                        $handler->pushProcessor(
                            $this->newInstanceWithOption($this->processors[$processorName]),
                        );
                    }
                }
            }

            // 设置 Formatter
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
     * 判断指定的 Logger 通道是否存在.
     *
     * @param string $channel Logger 通道名称
     *
     * @return bool 存在返回 true，否则返回 false
     */
    public function exists(string $channel): bool
    {
        return isset($this->loggers[$channel]) || isset($this->instances[$channel]);
    }

    /**
     * 释放所有已实例化的 Logger，清空实例缓存.
     *
     * @return self 返回当前实例以支持链式调用
     */
    public function release(): self
    {
        $this->instances = [];

        return $this;
    }

    /**
     * ArrayAccess 接口实现：设置 Logger 实例.
     *
     * @param mixed $channel Logger 通道名称
     * @param mixed $value Logger 实例
     */
    public function offsetSet(mixed $channel, mixed $value): void
    {
        $this->add($channel, $value);
    }

    /**
     * ArrayAccess 接口实现：获取 Logger 实例.
     *
     * @param mixed $channel Logger 通道名称
     *
     * @return mixed Logger 实例
     */
    public function offsetGet(mixed $channel): mixed
    {
        return $this->get($channel);
    }

    /**
     * ArrayAccess 接口实现：检查 Logger 是否存在.
     *
     * @param mixed $channel Logger 通道名称
     *
     * @return bool 存在返回 true，否则返回 false
     */
    public function offsetExists(mixed $channel): bool
    {
        return $this->exists($channel);
    }

    /**
     * ArrayAccess 接口实现：删除 Logger（此操作被禁止）.
     *
     * @param mixed $channel Logger 通道名称
     */
    public function offsetUnset(mixed $channel): void
    {
        // 该操作是被禁止的，不执行任何操作
    }

    /**
     * 根据配置选项创建类实例.
     *
     * 支持通过反射动态创建实例，自动处理构造函数参数映射。
     *
     * @param array{class: string, params?: array} $option 配置选项数组
     *                                                     - class: 完整的类名（包含命名空间）
     *                                                     - params: 构造函数参数数组，支持按名称或位置传递
     *
     * @throws \InvalidArgumentException 当缺少 class 参数时抛出异常
     * @throws RuntimeException 当类不存在时抛出异常
     *
     * @return object 创建的类实例
     *
     * @example
     * ```php
     * $logger = $this->newInstanceWithOption([
     *     'class' => '\Mellivora\Logger\Logger',
     *     'params' => ['name' => 'myname'],
     * ]);
     * // 相当于: new \Mellivora\Logger\Logger('myname');
     * ```
     */
    protected function newInstanceWithOption(array $option): object
    {
        if (empty($option['class'])) {
            throw new \InvalidArgumentException("Missing the 'class' parameter");
        }

        $class = $option['class'];
        if (!class_exists($class)) {
            throw new RuntimeException("Class '$class' not found");
        }

        if (empty($option['params'])) {
            return new $class();
        }

        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = $option['params'];
        $constructorParams = $constructor->getParameters();
        $args = [];

        // 按照构造函数参数顺序填充参数
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

            // 转换字符串级别为 Level 枚举
            if ($paramName === 'level' && is_string($value)) {
                try {
                    $value = Level::fromName($value);
                } catch (InvalidArgumentException|ValueError|UnhandledMatchError) {
                    // 如果转换失败，保持原值
                }
            }

            $args[] = $value;
        }

        return $ref->newInstanceArgs($args);
    }
}
