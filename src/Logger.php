<?php

namespace Mellivora\Logger;

use Mellivora\Logger\Filter\FilterInterFace;
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
     * Pushes a filter on to the stack.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function pushFilter($callback)
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException('Filters must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }

        array_unshift($this->filters, $callback);

        return $this;
    }

    /**
     * Pops a filter from the stack
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
     * @return FilterInterFace[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     */
    public function addRecord($level, $message, array $context = [])
    {
        foreach ($this->filters as $filter) {
            if (! $filter($level, $message, $context)) {
                return false;
            }
        }

        return parent::addRecord($level, $message, $context);
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return "Logger({$this->getName()})";
    }
}
