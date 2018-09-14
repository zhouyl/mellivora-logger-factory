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

        $name = $record['channel'];

        if (! isset(self::$points[$name])) {
            self::$points[$name] = microtime(true);
            $cost                = 0.0;
        } else {
            $current             = microtime(true);
            $cost                = round($current - self::$points[$name], 6);
            self::$points[$name] = $current;
        }

        $record['extra']['cost']              = $cost;
        $record['extra']['memory_usage']      = $this->formatBytes(memory_get_usage(true));
        $record['extra']['memory_peak_usage'] = $this->formatBytes(memory_get_peak_usage(true));

        return $record;
    }
}
