<?php

namespace Mellivora\Logger;

use Monolog\Handler\NullHandler;

/**
 * 日志工厂类 -  通过参数配置来管理项目的日志
 */
class LoggerFactory implements \ArrayAccess
{
    /**
     * 默认 logger channel
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * 已注册的 logger channel
     *
     * @var Logger[]
     */
    protected $loggers = [];

    /**
     * 配置数据，参考 examples/config.yaml 文件
     *
     * @var array
     */
    public static $config = [];

    /**
     * 单例实例
     *
     * @return LoggerFactory
     */
    protected static $instance = null;

    /**
     * 注册 logger 配置项
     *
     * @param array $config
     */
    public static function setupWithConfig(array $config)
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * 获取单例模式
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * 禁止 clone
     */
    private function __clone()
    {
    }

    /**
     * 设置默认的 logger 名称
     *
     * @param string $default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        if (! isset(self::$config[$default])) {
            throw new \RuntimeException("Call to undefined logger channel '$default'");
        }

        $this->default = $default;

        return $this;
    }

    /**
     * 获取默认 logger 实例
     *
     * @return \Mellivora\Logger\Logger
     */
    public function getDefault()
    {
        return $this->get($this->default);
    }

    /**
     * 注册 logger
     *
     * @param string                   $channel
     * @param \Mellivora\Logger\Logger $logger
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public function addLogger($channel, Logger $logger)
    {
        $this->loggers[$channel] = $logger;

        return $this;
    }

    /**
     * 根据名称获取 logger
     *
     * @param string $channel
     *
     * @return \Mellivora\Logger\Logger
     */
    public function getLogger($channel = null)
    {
        if ($channel === null) {
            $channel = $this->default;
        }

        // 如果 logger 已注册，直接返回
        if (array_key_exists($channel, $this->loggers)) {
            return $this->loggers[$channel];
        }

        // 根据配置文件，创建 logger 实例并注册
        if (isset(self::$config[$channel])) {
            $logger = new Logger($channel);

            $options = self::$config[$channel];

            if (isset($options['handlers'])) {
                $this->initLoggerHandlers($logger, $options['handlers']);
            }

            if (isset($options['processors'])) {
                $this->initLoggerProcessors($logger, $options['processors']);
            }

            if (isset($options['filters'])) {
                $this->initLoggerFilters($logger, $options['filters']);
            }
            $this->loggers[$channel] = $logger;

            return $logger;
        }

        // 未指定 default 配置，创建一个 NullHandler 的默认 logger
        if ($channel === $this->default) {
            $logger = new Logger($channel);
            $logger->pushHandler(new NullHandler);
            $this->loggers[$channel] = $logger;

            return $logger;
        }

        if ($channel === $this->default) {
            throw new \RuntimeException('The default logger not specified');
        }

        // 未指定配置的 logger，通过 clone 的方式，来根据 default 创建一个新的 logger
        // 以便于不同应用间的 logger 可以进行独立设置而不会冲突
        return $this->get($this->default)->withName($channel);
    }

    /**
     * 释放已注册的 logger，以刷新 logger
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public function refresh()
    {
        $this->loggers = [];

        return $this;
    }

    /**
     * 判断指定名称的 logger 是否存在
     *
     * @param string $channel
     *
     * @return bool
     */
    public function exists($channel)
    {
        return isset(self::$config[$channel]);
    }

    /**
     * 根据选项参数，创建类实例
     *
     *  option 需要以下参数：
     *      class:    用于指定完整的类名（包含 namespace 部分）
     *      argments: 用于指定参数列表，使用 key-value 对应类的构造方法参数列表
     *
     *  例如：
     *      $logger = $this->newInstanceWithOption([
     *          'class' => '\Mellivora\Logger\Logger',
     *          'argments' => ['name' => 'myname'],
     *      ]);
     *
     * 相当于： $logger = new \Mellivora\Logger\Logger('myname');
     *
     * @param array $option
     *
     * @throws \Exception
     *
     * @return object
     */
    protected function newInstanceWithOption($option)
    {
        if (empty($option['class'])) {
            throw new \InvalidArgumentException("Missing the 'class' argments");
        }

        $class = $option['class'];
        if (! class_exists($class)) {
            throw new \RuntimeException("Class '$class' not found");
        }

        $argments = empty($option['argments']) ? null : $option['argments'];
        if (empty($argments)) {
            return new $class;
        }

        $class = new \ReflectionClass($class);

        $data = [];
        foreach ($class->getConstructor()->getParameters() as $p) {
            $data[$p->getName()] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
        }

        return $class->newInstanceArgs(array_merge($data, $argments));
    }

    /**
     * @param \Mellivora\Logger\Logger $logger
     * @param array                    $handlers
     */
    protected function initLoggerHandlers(Logger $logger, array $handlers)
    {
        foreach ($handlers as $option) {
            $handler = $this->newInstanceWithOption($option);

            if (isset($option['processors'])) {
                foreach ($option['processors'] as $opt) {
                    $handler->pushProcessor($this->newInstanceWithOption($opt));
                }
            }

            if (isset($option['formatter'])) {
                $handler->setFormatter(
                    $this->newInstanceWithOption($option['formatter'])
                );
            }

            if (isset($option['level'])) {
                $handler->setLevel($option['level']);
            }

            $logger->pushHandler($handler);
        }
    }

    /**
     * @param \Mellivora\Logger\Logger $logger
     * @param array                    $processors
     */
    protected function initLoggerProcessors(Logger $logger, array $processors)
    {
        foreach ($processors as $option) {
            $logger->pushProcessor($this->newInstanceWithOption($option));
        }
    }

    /**
     * @param \Mellivora\Logger\Logger $logger
     * @param array                    $filters
     */
    protected function initLoggerFilters(Logger $logger, array $filters)
    {
        foreach ($filters as $option) {
            $logger->pushFilter($this->newInstanceWithOption($option));
        }
    }

    /**
     * 允许 LoggerFactory 被直接调用，调用的 logger 为 default 设置
     *
     * <code>
     * $loggerFactory->info('log message');
     * </code>
     *
     * @param string $method
     * @param mixed  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->getDefault(), $method], $args);
    }

    /*
     * 以下为 \ArrayAccess 接口要求实现的方法
     * 通过实现以下方法，允许像数组一样，访问该类
     */

    /**
     * 注册 logger
     *
     * @param string                   $channel
     * @param \Mellivora\Logger\Logger $logger
     *
     * @return $this
     */
    public function offsetSet($channel, Logger $logger)
    {
        return $this->addLogger($channel, $logger);
    }

    /**
     * 根据名称获取 logger
     *
     * @param string $channel
     *
     * @throws \RuntimeException
     *
     * @return \Mellivora\Logger\Logger
     */
    public function offsetGet($channel)
    {
        return $this->get($channel);
    }

    /**
     * 判断指定名称的 logger 是否注册
     *
     * @param string $channel
     *
     * @return bool
     */
    public function offsetExists($channel)
    {
        return $this->exists($channel);
    }

    /**
     * 删除 logger，该操作是被禁止的
     *
     * @param string $channel
     *
     * @return false
     */
    public function offsetUnset($channel)
    {
        return false;
    }
}
