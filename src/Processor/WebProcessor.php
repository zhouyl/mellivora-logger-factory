<?php

declare(strict_types=1);

namespace Mellivora\Logger\Processor;

use Monolog\Level;
use Monolog\LogRecord;

/**
 * Web Request Information Processor.
 *
 * Used to get detailed information about the current HTTP request,
 * including request headers, request method, client IP, etc.
 * Only works in web environment, adds request-related information to the log's extra field.
 */
class WebProcessor
{
    /**
     * Default server variable keys to collect.
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
     * Constructor.
     *
     * @param Level $level Minimum processing level, logs below this level will not be processed
     * @param array<string> $serverKeys Array of server variable keys to collect
     * @param array<string, mixed> $serverData Custom server data, uses $_SERVER when empty
     * @param array<string, mixed> $postData Custom POST data, uses $_POST when empty
     */
    public function __construct(
        protected readonly Level $level = Level::Debug,
        protected readonly array $serverKeys = self::DEFAULT_SERVER_KEYS,
        protected readonly array $serverData = [],
        protected readonly array $postData = [],
    ) {
    }

    /**
     * Process log record, adding web request information.
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
     * Get server data.
     *
     * @return array<string, mixed> Server data array
     */
    private function getServerData(): array
    {
        return $this->serverData !== [] ? $this->serverData : $_SERVER;
    }

    /**
     * Get POST data.
     *
     * @return array<string, mixed> POST data array
     */
    private function getPostData(): array
    {
        return $this->postData !== [] ? $this->postData : $_POST;
    }
}
