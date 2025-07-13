<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * Web 请求信息处理器.
 *
 * 用于获取当前 HTTP 请求的详细信息，包括请求头、请求方法、客户端IP等。
 * 仅在 Web 环境下工作，会在日志的 extra 字段中添加请求相关信息。
 */
class WebProcessor
{
    /**
     * 默认要收集的服务器变量键名.
     */
    private const DEFAULT_SERVER_KEYS = [
        'HTTP_USER_AGENT',
        'HTTP_HOST',
        'HTTP_REFERER',
        'REQUEST_URI',
        'REQUEST_METHOD',
        'REMOTE_ADDR',
    ];

    /**
     * 构造函数.
     *
     * @param Level $level 最低处理级别，低于此级别的日志不会被处理
     * @param array<string> $serverKeys 要收集的服务器变量键名数组
     * @param array<string, mixed> $serverData 自定义服务器数据，为空时使用 $_SERVER
     * @param array<string, mixed> $postData 自定义 POST 数据，为空时使用 $_POST
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
        protected readonly array $serverKeys = self::DEFAULT_SERVER_KEYS,
        protected readonly array $serverData = [],
        protected readonly array $postData = [],
    ) {
    }

    /**
     * 处理日志记录，添加 Web 请求信息.
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
        if (in_array(php_sapi_name(), ['cli', 'phpdbg'], true)) {
            return $record;
        }
        /** @codeCoverageIgnoreEnd */

        $serverData = $this->getServerData();
        foreach ($this->serverKeys as $key) {
            if (array_key_exists($key, $serverData)) {
                $record->extra[strtolower($key)] = $serverData[$key];
            }
        }

        $postData = $this->getPostData();
        if ($postData !== []) {
            $record->extra['post'] = $postData;
        }

        return $record;
    }

    /**
     * 获取服务器数据.
     *
     * @return array<string, mixed> 服务器数据数组
     */
    private function getServerData(): array
    {
        return $this->serverData !== [] ? $this->serverData : $_SERVER;
    }

    /**
     * 获取 POST 数据.
     *
     * @return array<string, mixed> POST 数据数组
     */
    private function getPostData(): array
    {
        return $this->postData !== [] ? $this->postData : $_POST;
    }
}
