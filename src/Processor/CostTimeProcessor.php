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

        $cost    = 0;
        $name    = $record['channel'];
        $hash    = md5(print_r($record, true));
        $current = microtime(true);

        if (! isset(self::$points[$name])) {
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => 0,
            ];
        }

        // 当多个 handler 同时调用时间计算时，可能会导致时间成本计算不准确
        elseif ($hash !== self::$points[$name]['hash']) {
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => round($current - self::$points[$name]['time'], 6),
            ];
        }

        $record['extra']['cost'] = self::$points[$name]['cost'];

        return $record;
    }
}
