<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * Script Process Information Processor.
 *
 * Used to get detailed information about the current command line process,
 * including process ID, script path, and full command.
 * Only works in CLI mode, adds process-related information to the log's extra field.
 */
class ScriptProcessor
{
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
     * Process log record, adding script process information.
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
             * Get script executable file path
             */
            $scriptPath = shell_exec("readlink /proc/$pid/exe 2>/dev/null");
            if ($scriptPath !== null) {
                $record->extra['script'] = trim($scriptPath);
            }

            // Get full command line
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
