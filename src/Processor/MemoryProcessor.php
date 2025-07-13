<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * 内存使用处理器.
 *
 * 用于记录当前内存使用情况，会在日志的 extra 字段中添加 'memory' 信息。
 * 支持格式化输出和真实内存使用量统计。
 */
class MemoryProcessor
{
    /**
     * 构造函数.
     *
     * @param Level $level 最低处理级别，低于此级别的日志不会被处理
     * @param bool $realUsage 是否获取真实内存使用量（包括系统分配但未使用的内存）
     * @param bool $useFormatting 是否格式化内存大小显示（如 1.5MB）
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
        protected readonly bool $realUsage = true,
        protected readonly bool $useFormatting = true,
    ) {
    }

    /**
     * 处理日志记录，添加内存使用信息.
     *
     * @param LogRecord $record 日志记录对象
     *
     * @return LogRecord 处理后的日志记录对象
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
     * 格式化字节数为可读的字符串.
     *
     * @param float|int $bytes 字节数
     *
     * @return int|string 格式化后的字符串（如 "1.5 MB"）或原始字节数
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
