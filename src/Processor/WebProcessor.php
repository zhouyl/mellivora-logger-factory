<?php

namespace Mellivora\Logger\Processor;

/**
 * 用于获取当前 Web 请求信息
 */
class WebProcessor
{
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

    public function __construct(array $serverKeys = null, array $serverData = null, array $postData = null)
    {
        $this->serverData = $serverData ?: $_SERVER;
        $this->postData   = $postData ?: $_POST;

        if ($serverKeys !== null) {
            $this->serverKeys = $serverKeys;
        }
    }

    public function __invoke(array $record)
    {
        if (! in_array(php_sapi_name(), ['cli', 'phpdbg'])) {
            foreach ($this->serverKeys as $key) {
                if (array_key_exists($key, $this->serverData)) {
                    $record['extra'][strtolower($key)] = $this->serverData[$key];
                }
            }

            if ($this->postData) {
                $record['extra']['post'] = $this->postData;
            }
        }

        return $record;
    }
}
