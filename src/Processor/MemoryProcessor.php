<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * Memory Usage Processor.
 *
 * Used to record current memory usage, adds 'memory' information to the log's extra field.
 * Supports formatted output and real memory usage statistics.
 */
class MemoryProcessor
{
    /**
     * Constructor.
     *
     * @param Level $level Minimum processing level, logs below this level will not be processed
     * @param bool $realUsage Whether to get real memory usage (including system allocated but unused memory)
     * @param bool $useFormatting Whether to format memory size display (e.g., 1.5MB)
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
        protected readonly bool $realUsage = true,
        protected readonly bool $useFormatting = true,
    ) {
    }

    /**
     * Process log record, adding memory usage information.
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

        $memoryUsage = memory_get_usage($this->realUsage);
        $record->extra['memory'] = $this->useFormatting
            ? $this->formatBytes($memoryUsage)
            : $memoryUsage;

        return $record;
    }

    /**
     * Format bytes to readable string.
     *
     * @param float|int $bytes Number of bytes
     *
     * @return int|string Formatted string (e.g., "1.5 MB") or raw byte count
     */
    protected function formatBytes(int|float $bytes): string|int
    {
        $bytes = (int) $bytes;

        if (!$this->useFormatting) {
            return $bytes;
        }

        return match (true) {
            $bytes >= 1024 * 1024 * 1024 => round($bytes / 1024 / 1024 / 1024, 2) . ' GB',
            $bytes >= 1024 * 1024 => round($bytes / 1024 / 1024, 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }
}
