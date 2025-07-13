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

/**
 * 日志类扩展 - 基于 Monolog 的自定义 Logger 实现.
 *
 * 提供了额外的功能：
 * - 全局日志级别控制
 * - 过滤器支持
 * - Handler 管理方法
 * - 异常日志记录
 */
class Logger extends MonoLogger
{
    /**
     * 过滤器数组，用于在记录日志前进行自定义过滤.
     *
     * @var array<callable(Level, string, array): bool>
     */
    protected array $filters = [];

    /**
     * 当前 Logger 的日志级别，低于此级别的日志将被忽略.
     */
    protected Level $level;

    /**
     * 构造函数.
     *
     * @param string $name Logger 名称
     * @param array $handlers Handler 数组
     * @param array $processors Processor 数组
     * @param null|DateTimeZone $timezone 时区设置
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
     * 将 Logger 转换为字符串表示.
     *
     * @return string Logger 的字符串表示
     */
    public function __toString(): string
    {
        return "Logger({$this->getName()})";
    }

    /**
     * 设置当前 Logger 的日志级别.
     *
     * 低于此级别的日志将不会被传递到 Handler 进行处理
     *
     * @param int|Level|string $level 日志级别，支持 Level 枚举、级别名称字符串或级别数值
     *
     * @throws InvalidArgumentException 当级别类型无效时抛出异常
     *
     * @return self 返回当前实例以支持链式调用
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
     * 获取当前 Logger 的日志级别.
     *
     * @return Level 当前设置的日志级别
     */
    public function getLevel(): Level
    {
        return $this->level;
    }

    /**
     * 根据类名获取 Handler 实例.
     *
     * 如果有多个相同类型的 Handler，将返回第一个匹配的实例
     *
     * @param string $class Handler 类名（支持完整类名或类名）
     *
     * @return mixed 匹配的 Handler 实例，未找到时返回 false
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
     * 根据类名移除 Handler.
     *
     * 如果有多个相同类型的 Handler，将全部被移除
     *
     * @param string $class Handler 类名
     *
     * @return bool 成功移除返回 true，未找到返回 false
     *
     * @example $logger->removeHandler(FileHandler::class)
     */
    public function removeHandler(string $class): bool
    {
        $removed = false;
        foreach ($this->handlers as $key => $handler) {
            if (is_a($handler, $class)) {
                unset($this->handlers[$key]);
                $this->handlers = array_values($this->handlers); // 重新索引
                $removed = true;
                break; // 只移除第一个匹配的
            }
        }

        return $removed;
    }

    /**
     * 添加一个过滤器.
     *
     * 过滤器是一个可调用对象，用于在记录日志前进行自定义过滤。
     * 过滤器接收三个参数：Level $level, string $message, array $context
     * 返回 true 表示允许记录，false 表示拒绝记录。
     *
     * @param callable(Level, string, array): bool $callback 过滤器回调函数
     *
     * @throws InvalidArgumentException 当回调不可调用时抛出异常
     *
     * @return self 返回当前实例以支持链式调用
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
     * 弹出并获取第一个注册的过滤器.
     *
     * @throws LogicException 当过滤器栈为空时抛出异常
     *
     * @return callable 过滤器回调函数
     */
    public function popFilter(): callable
    {
        if ($this->filters === []) {
            throw new LogicException('You tried to pop from an empty filter stack.');
        }

        return array_shift($this->filters);
    }

    /**
     * 获取所有已注册的过滤器.
     *
     * @return array<callable(Level, string, array): bool> 过滤器数组
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * 添加日志记录.
     *
     * 重写父类方法以支持自定义级别控制和过滤器功能
     *
     * @param int|Level $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param null|DateTimeInterface $datetime 日志时间
     *
     * @return bool 是否成功记录日志
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

        // 应用过滤器
        foreach ($this->filters as $filter) {
            if (!$filter($levelObj, $message, $context)) {
                return false;
            }
        }

        return parent::addRecord($levelObj, $message, $context, $datetime);
    }

    /**
     * 记录异常日志.
     *
     * 自动提取异常的详细信息（类名、错误码、消息、文件、行号）并记录到日志中
     *
     * @param Throwable $ex 异常对象
     * @param int|Level|string $level 日志级别，默认为 Error
     *
     * @return bool 是否成功记录日志
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
     * 解析字符串级别.
     */
    private function parseStringLevel(string $level): Level
    {
        try {
            return Level::fromName($level);
        } catch (UnhandledMatchError|\ValueError $e) {
            throw new InvalidArgumentException("Invalid level string: {$level}", 0, $e);
        }
    }
}
