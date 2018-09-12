<?php

namespace Mellivora\Logger\Processor;

/**
 * 用于获取时间成本消耗
 */
class CostTimeProcessor
{
    protected static $points = [];

    public function __invoke(array $record)
    {
        $name = $record['channel'];

        if (! isset(self::$points[$name])) {
            self::$points[$name] = microtime(true);
            $cost                = 0.0;
        } else {
            $current                 = microtime(true);
            $cost                    = round($current - self::$points[$name], 8);
            self::$points[$name]     = $current;
        }

        $record['extra']['cost'] = $cost.' s';

        return $record;
    }
}
