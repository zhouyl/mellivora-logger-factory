<?php

namespace Mellivora\Logger\Processor;

use Monolog\Logger;
use Monolog\Processor\MemoryProcessor;

/**
 * 用于获取性能分析，可以获取内存消耗及时间成本
 */
class ProfilerProcessor extends MemoryProcessor
{
    protected static $points = [];

    protected $level;

    public function __construct($level = Logger::DEBUG)
    {
        $this->level = Logger::toMonologLevel($level);
        parent::__construct();
    }

    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level) {
            return $record;
        }

        $cost    = 0;
        $name    = $record['channel'];
        $hash    = md5(var_export($record, true));
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

        $record['extra']['cost']              = self::$points[$name]['cost'];
        $record['extra']['memory_usage']      = $this->formatBytes(memory_get_usage(true));
        $record['extra']['memory_peak_usage'] = $this->formatBytes(memory_get_peak_usage(true));

        return $record;
    }
}
