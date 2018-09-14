<?php

namespace Mellivora\Logger\Processor;

use Monolog\Logger;

/**
 * 用于获取当前 Web 请求信息
 */
class WebProcessor
{
    protected $level;

    protected $serverData = [];

    protected $serverKeys = [
        'HTTP_USER_AGENT',
        'HTTP_HOST',
        'HTTP_REFERER',
        'REQUEST_URI',
        'REQUEST_METHOD',
        'REMOTE_ADDR',
    ];

    protected $postData = [];

    public function __construct(
        $level = Logger::DEBUG,
        array $serverKeys = null,
        array $serverData = null,
        array $postData = null
    ) {
        $this->level      = Logger::toMonologLevel($level);
        $this->serverData = $serverData ?: $_SERVER;
        $this->postData   = $postData ?: $_POST;

        if ($serverKeys !== null) {
            $this->serverKeys = $serverKeys;
        }
    }

    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level || in_array(php_sapi_name(), ['cli', 'phpdbg'])) {
            return $record;
        }

        foreach ($this->serverKeys as $key) {
            if (array_key_exists($key, $this->serverData)) {
                $record['extra'][strtolower($key)] = $this->serverData[$key];
            }
        }

        if ($this->postData) {
            $record['extra']['post'] = $this->postData;
        }

        return $record;
    }
}
