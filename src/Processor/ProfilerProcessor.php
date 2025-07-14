<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\MemoryProcessor as MonologMemoryProcessor;

/**
 * Performance Profiler Processor.
 *
 * Comprehensive performance analysis processor that combines time cost and memory usage statistics.
 * Adds 'cost', 'memory_usage', and 'memory_peak_usage' information to the log's extra field.
 */
class ProfilerProcessor extends MonologMemoryProcessor
{
    /**
     * Time point record array, stored by channel name.
     *
     * @var array<string, array{time: float, hash: string, cost: float}>
     */
    protected static array $points = [];

    /**
     * Constructor.
     *
     * @param Level $level Minimum processing level, logs below this level will not be processed
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
    ) {
        parent::__construct();
    }

    /**
     * Process log record, adding performance analysis information.
     *
     * @param LogRecord $record Log record object
     *
     * @return LogRecord Processed log record object
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->level->value < $this->level->value) {
            return $record;
        }

        // Calculate time cost
        $name = $record->channel;
        $hash = md5(serialize($record->toArray()));
        $current = microtime(true);

        if (!isset(self::$points[$name])) {
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => 0.0,
            ];
        } elseif ($hash !== self::$points[$name]['hash']) {
            // Only update time cost when record content changes
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => round($current - self::$points[$name]['time'], 6),
            ];
        }

        // Add performance information
        $record->extra['cost'] = self::$points[$name]['cost'];
        $record->extra['memory_usage'] = $this->formatBytes(memory_get_usage(true));
        $record->extra['memory_peak_usage'] = $this->formatBytes(memory_get_peak_usage(true));

        return $record;
    }
}
