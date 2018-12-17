<?php

namespace Mellivora\Logger;

use Monolog\Handler\NullHandler;
use Noodlehaus\Config;
use Psr\Log\LoggerInterface;

/**
 * 日志工厂类 -  通过参数配置来管理项目的日志
 */
class LoggerFactory implements \ArrayAccess
{
    use TraitSingleton;

    /**
     * 用于来辅助日志文件定位项目根目录
     *
     * @var string
     */
    protected static $rootPath;

    /**
     * 设置项目根目录
     *
     * @param string $path
     */
    public static function setRootPath($path)
    {
        self::$rootPath = realpath($path);
    }

    /**
     * 获取项目根目录
     *
     * @return string
     */
    public static function getRootPath()
    {
        if (! self::$rootPath) {
            foreach (['.', '../../..'] as $p) {
                $path = realpath(dirname(__DIR__) . '/' . $p);
                if (is_dir($path) && is_dir($path . '/vendor')) {
                    self::setRootPath($path);
                }
            }
        }

        return self::$rootPath;
    }

    /**
     * 根据配置，实例化创建一个 logger factory
     *
     * @param array $config
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public static function build(array $config)
    {
        return (new self($config))->registerSingleton();
    }

    /**
     * 根据配置文件，实例化创建一个 logger factory
     * 需要 Noodlehaus\Config 开源组件的支持
     * 可支持的文件类型包括 php/yaml/json/ini/xml 格式
     *
     * @param string $configFile
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public static function buildWith($configFile)
    {
        if (! class_exists(Config::class)) {
            throw new \Exception(
                'Require components: hassankhan/config ' .
                '(ref: https://github.com/hassankhan/config)'
            );
        }

        return self::build(Config::load($configFile)->all());
    }

    /**
     * 默认 logger channel
     *
     * @var string
     */
    protected $default;

    /**
     * 定义了 logger formatter 配置选项
     *
     * @var array
     */
    protected $formatters = [];

    /**
     * 定义了 logger processor 配置选项
     *
     * @var array
     */
    protected $processors = [];

    /**
     * 定义了 logger handler 配置选项
     *
     * @var array
     */
    protected $handlers   = [];

    /**
     * 定义了所有的 logger channel
     *
     * @var array
     */
    protected $loggers    = [];

    /**
     * 已实例化的 logger
     *
     * @var array
     */
    protected $instnaces  = [];

    /**
     * @param array $config
     */
    public function __construct(array $config=[])
    {
        $keys = ['formatters', 'processors',  'handlers', 'loggers'];
        foreach ($keys as $key) {
            if (isset($config[$key]) && is_array($config[$key])) {
                $this->{$key} = $config[$key];
            }
        }
    }

    /**
     * 设置默认的 logger 名称
     *
     * @param string $default
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function setDefault($default)
    {
        if (! isset($this->loggers[$default])) {
            throw new \RuntimeException(
                "Call to undefined logger channel '$default'"
            );
        }

        $this->default = $default;

        return $this;
    }

    /**
     * 获取默认 logger 名称
     *
     * @return \Mellivora\Logger\Logger
     */
    public function getDefault()
    {
        if (empty($this->default)) {
            if (count($this->loggers)) {
                $this->default = current(array_keys($this->loggers));
            } else {
                $this->default = 'default';
            }
        }

        return $this->default;
    }

    /**
     * 注册一个 logger 实例
     *
     * @param string                   $channel
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public function add($channel, LoggerInterface $logger)
    {
        $this->instances[$channel] = $logger;

        return $this;
    }

    /**
     * 根据名称及预定义配置，获取一个 logger
     *
     * @param string $channel
     *
     * @return \Mellivora\Logger\Logger
     */
    public function get($channel = null)
    {
        if (empty($channel)) {
            $channel = $this->getDefault();
        }

        if (! isset($this->instances[$channel])) {
            if (! isset($this->loggers[$channel])) {
                $channel = $this->getDefault();
            }

            $this->instances[$channel] = $this->make(
                $channel,
                isset($this->loggers[$channel]) ? $this->loggers[$channel] : null
            );
        }

        return $this->instances[$channel];
    }

    /**
     * 根据已注册的 handlers 配置，即时生成一个 logger
     *
     * @param string            $channel
     * @param null|array|string $handlers
     *
     * @return \Mellivora\Logger\Logger
     */
    public function make($channel, $handlers=null)
    {
        $logger = new Logger($channel);

        if (empty($handlers)) {
            return $logger->pushHandler(new NullHandler);
        }

        foreach (is_array($handlers) ? $handlers : [$handlers] as $handlerName) {
            if (! isset($this->handlers[$handlerName])) {
                continue;
            }

            $option  = $this->handlers[$handlerName];
            $handler = $this->newInstanceWithOption($option);

            if (isset($option['processors'])) {
                foreach ($option['processors'] as $processorName) {
                    if (isset($this->processors[$processorName])) {
                        $handler->pushProcessor(
                            $this->newInstanceWithOption($this->processors[$processorName])
                        );
                    }
                }
            }

            if (isset($option['formatter'], $this->formatters[$option['formatter']])) {
                $handler->setFormatter(
                    $this->newInstanceWithOption($this->formatters[$option['formatter']])
                );
            }

            $logger->pushHandler($handler);
        }

        return $logger;
    }

    /**
     * 判断指定的 logger channel 是否存在
     *
     * @param string $channel
     *
     * @return bool
     */
    public function exists($channel)
    {
        return isset($this->loggers[$channel]) || isset($this->instances[$channel]);
    }

    /**
     * 释放已注册的 logger，以刷新 logger
     *
     * @return \Mellivora\Logger\LoggerFactory
     */
    public function release()
    {
        $this->instances = [];

        return $this;
    }

    /**
     * 注册一个 logger
     *
     * @param string                   $channel
     * @param \Mellivora\Logger\Logger $logger
     *
     * @return $this
     */
    public function offsetSet($channel, $logger)
    {
        return $this->add($channel, $logger);
    }

    /**
     * 根据名称获取 logger
     *
     * @param string $channel
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

    /**
     * 根据选项参数，创建类实例
     *
     *  option 需要以下参数：
     *      class:  用于指定完整的类名（包含 namespace 部分）
     *      params: 用于指定参数列表，使用 key-value 对应类的构造方法参数列表
     *
     *  例如：
     *      $logger = $this->newInstanceWithOption([
     *          'class' => '\Mellivora\Logger\Logger',
     *          'params' => ['name' => 'myname'],
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
            throw new \InvalidArgumentException("Missing the 'class' parameter");
        }

        $class = $option['class'];
        if (! class_exists($class)) {
            throw new \RuntimeException("Class '$class' not found");
        }

        if (empty($option['params'])) {
            return new $class;
        }

        $ref    = new \ReflectionClass($class);
        $values = [];
        foreach ($ref->getConstructor()->getParameters() as $p) {
            $values[$p->getName()] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
        }

        return $ref->newInstanceArgs(array_merge($values, $option['params']));
    }
}
