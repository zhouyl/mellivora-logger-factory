<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\MemoryProcessor as MonologMemoryProcessor;

/**
 * 性能分析处理器.
 *
 * 综合性能分析处理器，结合了时间成本和内存使用情况的统计。
 * 会在日志的 extra 字段中添加 'cost'、'memory_usage' 和 'memory_peak_usage' 信息。
 */
class ProfilerProcessor extends MonologMemoryProcessor
{
    /**
     * 时间点记录数组，按通道名称存储.
     *
     * @var array<string, array{time: float, hash: string, cost: float}>
     */
    protected static array $points = [];

    /**
     * 构造函数.
     *
     * @param Level $level 最低处理级别，低于此级别的日志不会被处理
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
    ) {
        parent::__construct();
    }

    /**
     * 处理日志记录，添加性能分析信息.
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

        // 计算时间成本
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
            // 只有当记录内容发生变化时才更新时间成本
            self::$points[$name] = [
                'time' => $current,
                'hash' => $hash,
                'cost' => round($current - self::$points[$name]['time'], 6),
            ];
        }

        // 添加性能信息
        $record->extra['cost'] = self::$points[$name]['cost'];
        $record->extra['memory_usage'] = $this->formatBytes(memory_get_usage(true));
        $record->extra['memory_peak_usage'] = $this->formatBytes(memory_get_peak_usage(true));

        return $record;
    }
}
