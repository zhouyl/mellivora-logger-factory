<?php

namespace Mellivora\Logger\Processor;

use Monolog\Processor\MemoryProcessor;

/**
 * 用于获取性能分析，可以获取内存消耗及时间成本
 */
class ProfilerProcessor extends MemoryProcessor
{
    protected static $points = [];

    public function __invoke(array $record)
    {
        $name = $record['channel'];

        if (! isset(self::$points[$name])) {
            self::$points[$name] = microtime(true);
            $cost                = 0.0;
        } else {
            $current             = microtime(true);
            $cost                = round($current - self::$points[$name], 8);
            self::$points[$name] = $current;
        }

        $record['extra']['cost']              = $cost.' s';
        $record['extra']['memory_usage']      = $this->formatBytes(memory_get_usage($this->realUsage));
        $record['extra']['memory_peak_usage'] = $this->formatBytes(memory_get_peak_usage($this->realUsage));

        return $record;
    }
}
