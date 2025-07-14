<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * Cost Time Processor.
 *
 * Used to calculate and record time intervals between log records, helping analyze program performance.
 * Adds 'cost' information to the log's extra field, representing the time consumed since the last record (seconds).
 */
class CostTimeProcessor
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
    }

    /**
     * Process log record, adding time cost information.
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
            // This avoids duplicate calculations when multiple Handlers process the same record simultaneously
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => round($current - self::$points[$name]['time'], 6),
            ];
        }

        $record->extra['cost'] = self::$points[$name]['cost'];

        return $record;
    }
}
