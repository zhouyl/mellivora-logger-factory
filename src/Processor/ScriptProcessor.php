<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * 脚本进程信息处理器.
 *
 * 用于获取当前命令行进程的详细信息，包括进程ID、脚本路径和完整命令。
 * 仅在 CLI 模式下工作，会在日志的 extra 字段中添加进程相关信息。
 */
class ScriptProcessor
{
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
     * 处理日志记录，添加脚本进程信息.
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

        // @codeCoverageIgnoreStart
        if (php_sapi_name() !== 'cli') {
            return $record;
        }
        /** @codeCoverageIgnoreEnd */

        $pid = getmypid();
        $record->extra['pid'] = $pid;

        if (function_exists('shell_exec') && $pid !== false) {
            /**
             * @codeCoverageIgnoreStart
             * 获取脚本可执行文件路径
             */
            $scriptPath = shell_exec("readlink /proc/$pid/exe 2>/dev/null");
            if ($scriptPath !== null) {
                $record->extra['script'] = trim($scriptPath);
            }

            // 获取完整的命令行
            $command = shell_exec(
                "ps -eo pid,cmd | grep $pid | grep -v grep | " .
                "awk 'sub(\$1,\"\")' 2>/dev/null",
            );
            if ($command !== null) {
                $record->extra['command'] = trim($command);
            }
            // @codeCoverageIgnoreEnd
        }

        return $record;
    }
}
