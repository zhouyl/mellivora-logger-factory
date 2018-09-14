<?php

namespace Mellivora\Logger\Processor;

use Monolog\Logger;

/**
 * 用于获取当前命令行进程信息
 */
class ScriptProcessor
{
    protected $level;

    public function __construct($level = Logger::DEBUG)
    {
        $this->level = Logger::toMonologLevel($level);
    }

    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level || php_sapi_name() !== 'cli') {
            return $record;
        }

        $pid = getmypid();

        $record['extra']['pid'] = $pid;

        if (function_exists('shell_exec')) {
            $record['extra']['script']  = trim(`readlink /proc/$pid/exe`);
            $record['extra']['command'] = trim(`ps -eo pid,cmd | grep $pid | grep -v grep | awk 'sub($1,"")'`);
        }

        return $record;
    }
}
