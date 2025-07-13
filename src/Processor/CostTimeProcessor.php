<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * 时间成本处理器.
 *
 * 用于计算和记录日志记录之间的时间间隔，帮助分析程序性能。
 * 会在日志的 extra 字段中添加 'cost' 信息，表示距离上次记录的时间消耗（秒）。
 */
class CostTimeProcessor
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
    }

    /**
     * 处理日志记录，添加时间成本信息.
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
            // 这样可以避免多个 Handler 同时处理同一条记录时的重复计算
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
