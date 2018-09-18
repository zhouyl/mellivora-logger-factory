<?php

namespace Mellivora\Logger;

use Monolog\Logger as MonoLogger;

/**
 * 日志类扩展 - 新增一些自定义的方法
 */
class Logger extends MonoLogger
{
    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var int
     */
    protected $level = Logger::DEBUG;

    /**
     * 设置一个针对当前 logger 的全局 level，低于这个 level 的日志将不被传递到 handler
     *
     * @param int|string $level Level or level name
     *
     * @return self
     */
    public function setLevel($level)
    {
        $this->level = Logger::toMonologLevel($level);

        return $this;
    }

    /**
     * 获取当前 logger 的 level
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * 根据 class 名称获取 handler
     * (如果有多个同一实例的 handler，将根据顺序返回其它一个)
     *
     * 例如： $logger->getHandler(FileHandler::class)
     *
     * @param string $class
     *
     * @return mixed
     */
    public function getHandler($class)
    {
        foreach ($this->handlers as $handler) {
            if (is_a($handler, $class)) {
                return $handler;
            }
        }

        return false;
    }

    /**
     * 根据 class 名称移除 handler
     * (如果有多个同一实例的 handler，将全部被移除)
     *
     * 例如： $logger->removeHandler(FileHandler::class)
     *
     * @param string $class
     *
     * @return true
     */
    public function removeHandler($class)
    {
        foreach ($this->handlers as $key => $handler) {
            if (is_a($handler, $class)) {
                unset($this->handlers[$key]);
            }
        }

        return true;
    }

    /**
     * 新增一个过滤器 filter
     *
     * @param callable $callback
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function pushFilter($callback)
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException(
                'Filters must be valid callables ' .
                '(callback or object with an __invoke method), ' .
                var_export($callback, true) . ' given'
            );
        }

        array_unshift($this->filters, $callback);

        return $this;
    }

    /**
     * 弹出并获取第一个注册的过滤器
     *
     * @throws \LogicException
     *
     * @return callable
     */
    public function popFilter()
    {
        if (! $this->filters) {
            throw new \LogicException('You tried to pop from an empty filter stack.');
        }

        return array_shift($this->filters);
    }

    /**
     * 获取所有的过滤器
     *
     * @return callable[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * {@inheritdoc}
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($level < $this->level) {
            return false;
        }

        // 增加过滤器的调用
        foreach ($this->filters as $filter) {
            if (! $filter($level, $message, $context)) {
                return false;
            }
        }

        return parent::addRecord($level, $message, $context);
    }

    /**
     * 新增一个异常消息，并自动获取消息的摘要信息并存储到 extra 中
     *
     * @param \Exception|\Throwable $ex
     * @param int                   $level
     *
     * @return bool
     */
    public function addException($ex, $level = self::ERROR)
    {
        if ($ex instanceof \Throwable || $ex instanceof \Exception) {
            return $this->addRecord(
                $level,
                $ex->getMessage(),
                [
                    'exception' => get_class($ex),
                    'code'      => $ex->getCode(),
                    'message'   => $ex->getMessage(),
                    'file'      => $ex->getFile(),
                    'line'      => $ex->getLine(),
                ]
            );
        }

        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "Logger({$this->getName()})";
    }
}
