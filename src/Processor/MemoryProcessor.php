<?php

namespace Mellivora\Logger\Processor;

use Monolog\Logger;

class MemoryProcessor
{
    protected $level;
    protected $realUsage;
    protected $useFormatting;

    public function __construct($level = Logger::DEBUG, $realUsage = true, $useFormatting=true)
    {
        $this->level         = Logger::toMonologLevel($level);
        $this->realUsage     = (bool) $realUsage;
        $this->useFormatting = (bool) $useFormatting;
    }

    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level) {
            return $record;
        }

        $record['extra']['memory'] = $this->formatBytes(memory_get_usage($this->realUsage));

        return $record;
    }

    protected function formatBytes($bytes)
    {
        $bytes = (int) $bytes;

        if (! $this->useFormatting) {
            return $bytes;
        }

        if ($bytes > 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }
        if ($bytes > 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
