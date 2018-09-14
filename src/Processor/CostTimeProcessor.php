<?php

namespace Mellivora\Logger\Processor;

use Monolog\Logger;

/**
 * 用于获取时间成本消耗
 */
class CostTimeProcessor
{
    protected static $points = [];

    protected $level;

    public function __construct($level = Logger::DEBUG)
    {
        $this->level = Logger::toMonologLevel($level);
    }

    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level) {
            return $record;
        }

        $name = $record['channel'];

        if (! isset(self::$points[$name])) {
            self::$points[$name] = microtime(true);
            $cost                = 0.0;
        } else {
            $current             = microtime(true);
            $cost                = round($current - self::$points[$name], 6);
            self::$points[$name] = $current;
        }

        $record['extra']['cost'] = $cost;

        return $record;
    }
}
