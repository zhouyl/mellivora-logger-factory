<?php

namespace Mellivora\Logger;

trait TraitSingleton
{
    /**
     * 当前使用的单例实例
     *
     * @var object
     */
    protected static $singleton;

    /**
     * 允许单例模式调用
     *
     * @return object
     */
    public static function singleton()
    {
        if (! self::$singleton instanceof self) {
            throw new \RuntimeException('Singleton instance is not registered');
        }

        return self::$singleton;
    }

    /**
     * 将当前实例注册为单例
     *
     * @return object
     */
    public function registerSingleton()
    {
        self::$singleton = $this;

        return $this;
    }
}
