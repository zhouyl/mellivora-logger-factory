<?php

declare(strict_types=1);

namespace Mellivora\Logger\Handler;

use Mellivora\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use UnexpectedValueException;

/**
 * 命名轮转文件处理器.
 *
 * 根据 Logger 的通道名称生成不同的日志文件，并支持自动轮转功能。
 * 文件名格式：{basename}-{channel}-{date}.{extension}
 *
 * 特性：
 * - 按通道名称分离日志文件
 * - 按日期自动轮转
 * - 支持文件大小限制
 * - 支持备份文件数量限制
 * - 支持缓冲写入
 *
 * @example
 * ```php
 * $logger = new Logger('order');
 * $logger->pushHandler(new NamedRotatingFileHandler('/tmp/app.log'));
 * $logger->info('hello');
 * // 将生成 /tmp/app-order-2024-01-01.log 文件
 * ```
 */
class NamedRotatingFileHandler extends StreamHandler
{
    /**
     * 日志文件名模板
     */
    protected string $filename;

    /**
     * 单个文件最大字节数.
     */
    protected int $maxBytes;

    /**
     * 备份文件数量.
     */
    protected int $backupCount;

    /**
     * 缓冲区大小（记录数）.
     */
    protected int $bufferSize;

    /**
     * 日期格式字符串.
     */
    protected string $dateFormat;

    /**
     * 文件流缓存，按文件路径存储.
     *
     * @var array<string, resource>
     */
    private array $streams = [];

    /**
     * 日志记录缓冲区，按通道名称存储.
     *
     * @var array<string, array>
     */
    private array $buffers = [];

    /**
     * 当前处理的通道名称.
     */
    private ?string $current = null;

    /**
     * 目录是否已创建的标记.
     */
    private bool $dirCreated = false;

    /**
     * 是否已初始化.
     */
    private bool $initialized = false;

    /**
     * 构造函数.
     *
     * @param string $filename 日志文件名模板，支持相对路径和绝对路径
     * @param int $maxBytes 单个日志文件最大字节数，默认 100MB，0 表示不限制
     * @param int $backupCount 保留的备份文件数量，默认 10 个，0 表示不保留备份
     * @param int $bufferSize 缓冲区大小（记录数），默认 0 表示不使用缓冲
     * @param string $dateFormat 日期格式字符串，用于生成文件名中的日期部分
     * @param int|Level $level 最低日志级别
     * @param bool $bubble 是否向上传递日志记录
     * @param null|int $filePermission 文件权限，null 使用系统默认
     * @param bool $useLocking 是否使用文件锁
     */
    public function __construct(
        string $filename,
        int $maxBytes = 100000000,
        int $backupCount = 10,
        int $bufferSize = 0,
        string $dateFormat = 'Y-m-d',
        int|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
    ) {
        // 处理相对路径
        if (!str_starts_with($filename, '/') && !str_starts_with($filename, 'file://')) {
            $rootPath = LoggerFactory::getRootPath();
            $filename = $rootPath ? $rootPath . '/' . $filename : $filename;
        }

        $this->filename = $filename;
        $this->maxBytes = $maxBytes;
        $this->backupCount = $backupCount;
        $this->bufferSize = $bufferSize;
        $this->dateFormat = $dateFormat;

        parent::__construct(
            $this->getFilename(),
            $level,
            $bubble,
            $filePermission,
            $useLocking,
        );
    }

    /**
     * 根据 logger channel 获取当前日志文件名.
     *
     * @param string $channel
     *
     * @return string
     */
    public function getFilename(string $channel = ''): string
    {
        if ($this->dirCreated) {
            $logPath = dirname($this->filename);

            // @codeCoverageIgnoreStart
            if (!is_dir($logPath)) {
                @mkdir($logPath, 0o777, true);
            }

            if (!is_writable($logPath)) {
                throw new UnexpectedValueException('Unable to write to the log path: ' . $logPath);
            }
            // @codeCoverageIgnoreEnd

            $this->dirCreated = true;
        }

        return str_replace(
            ['%date%', '%channel%'],
            [date($this->dateFormat), $channel],
            $this->filename,
        );
    }

    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if (!$this->initialized) {
            // @codeCoverageIgnoreStart
            register_shutdown_function([$this, 'close']);
            // @codeCoverageIgnoreEnd
            $this->initialized = true;
        }

        $record = $this->processRecord($record);
        $channel = $record->channel;

        $formatted = $this->getFormatter()->format($record);

        if ($this->bufferSize) {
            $this->buffers[$channel][] = ['record' => $record, 'formatted' => $formatted];
            if (count($this->buffers[$channel]) > $this->bufferSize) {
                $this->flush($channel);
            }
        } else {
            // @codeCoverageIgnoreStart
            $this->write($record);
            // @codeCoverageIgnoreEnd
        }

        return false === $this->bubble;
    }

    /**
     * 刷新缓冲数据到日志文件.
     *
     * @param string|null $channel
     */
    public function flush(string $channel = null): void
    {
        if ($channel === null) {
            array_map([$this, 'flush'], array_keys($this->buffers));

            return;
        }

        if ($this->buffers[$channel]) {
            // @codeCoverageIgnoreStart
            foreach ($this->buffers[$channel] as $item) {
                $this->write($item['record']);
            }
            // @codeCoverageIgnoreEnd

            $this->buffers[$channel] = [];
        }
    }

    public function close(): void
    {
        $this->bufferSize && $this->flush();

        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                // @codeCoverageIgnoreStart
                fclose($stream);
                // @codeCoverageIgnoreEnd
            }
        }

        $this->url = null;
        $this->stream = null;
        $this->streams = [];
    }

    protected function write(LogRecord $record): void
    {
        $channel = $record->channel;

        if ($channel !== $this->current || !$this->stream) {
            $this->url = $this->getFilename($channel);

            if (isset($this->streams[$this->url])) {
                $this->stream = $this->streams[$this->url];
            } else {
                $this->stream = null;
            }
        }

        // @codeCoverageIgnoreStart
        parent::write($record);
        // @codeCoverageIgnoreEnd

        // add to stream buffer
        $this->streams[$this->url] = $this->stream;
        $this->current = $channel;

        // rotates the files
        // @codeCoverageIgnoreStart
        $this->rotate();
        // @codeCoverageIgnoreEnd
    }

    /**
     * 日志分片备份，仅保留指定数量.
     */
    protected function rotate(): void
    {
        if (!$this->maxBytes) {
            return;
        }

        /** @codeCoverageIgnoreStart */
        $size = fstat($this->stream)['size'];
        if ($size < $this->maxBytes) {
            return;
        }
        // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        fclose($this->stream);
        unset($this->streams[$this->url]);
        // @codeCoverageIgnoreEnd
        $this->stream = null;

        // matching all log files
        $fileInfo = pathinfo($this->url);
        $baseFile = "{$this->url}.";
        $logFiles = glob("{$this->url}.*");

        // sorting the files by name to remove or rename the older ones
        // @codeCoverageIgnoreStart
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });
        /** @codeCoverageIgnoreEnd */

        // remove the older files
        $offset = count($logFiles) - $this->backupCount + 1;
        if ($this->backupCount && $offset > 0) {
            // @codeCoverageIgnoreStart
            foreach (array_slice($logFiles, 0, $offset) as $logfile) {
                @unlink($logfile);
            }
            /** @codeCoverageIgnoreEnd */
            $logFiles = array_slice($logFiles, $offset);
        }

        // rename the older files
        // @codeCoverageIgnoreStart
        for ($i = count($logFiles); $i > 0; --$i) {
            $this->rename($baseFile . $i, $baseFile . ($i + 1));
        }

        // rename current log file
        $this->rename($this->url, $baseFile . '1');
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @codeCoverageIgnore
     */
    protected function rename(string $source, string $target): void
    {
        if (is_file($source)) {
            if (is_file($target)) {
                @unlink($target);
            }
            @rename($source, $target);
        }
    }
}
