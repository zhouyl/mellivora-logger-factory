<?php

declare(strict_types=1);

namespace Mellivora\Logger\Handler;

use Mellivora\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use UnexpectedValueException;

/**
 * Named Rotating File Handler.
 *
 * Generates different log files based on Logger channel names and supports automatic rotation.
 * File name format: {basename}-{channel}-{date}.{extension}
 *
 * Features:
 * - Separate log files by channel name
 * - Automatic rotation by date
 * - Support for file size limits
 * - Support for backup file count limits
 * - Support for buffered writing
 *
 * @example
 * ```php
 * $logger = new Logger('order');
 * $logger->pushHandler(new NamedRotatingFileHandler('/tmp/app.log'));
 * $logger->info('hello');
 * // Will generate /tmp/app-order-2024-01-01.log file
 * ```
 */
class NamedRotatingFileHandler extends StreamHandler
{
    /**
     * Log file name template
     */
    protected string $filename;

    /**
     * Maximum bytes per file.
     */
    protected int $maxBytes;

    /**
     * Number of backup files.
     */
    protected int $backupCount;

    /**
     * Buffer size (number of records).
     */
    protected int $bufferSize;

    /**
     * Date format string.
     */
    protected string $dateFormat;

    /**
     * File stream cache, stored by file path.
     *
     * @var array<string, resource>
     */
    private array $streams = [];

    /**
     * Log record buffer, stored by channel name.
     *
     * @var array<string, array>
     */
    private array $buffers = [];

    /**
     * Currently processing channel name.
     */
    private ?string $current = null;

    /**
     * Flag indicating whether directory has been created.
     */
    private bool $dirCreated = false;

    /**
     * Whether initialization has been completed.
     */
    private bool $initialized = false;

    /**
     * Constructor.
     *
     * @param string $filename Log file name template, supports relative and absolute paths
     * @param int $maxBytes Maximum bytes per log file, default 100MB, 0 means no limit
     * @param int $backupCount Number of backup files to retain, default 10, 0 means no backup
     * @param int $bufferSize Buffer size (number of records), default 0 means no buffering
     * @param string $dateFormat Date format string for generating date part in filename
     * @param int|Level $level Minimum log level
     * @param bool $bubble Whether to bubble log records up
     * @param null|int $filePermission File permission, null uses system default
     * @param bool $useLocking Whether to use file locking
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
        // Handle relative paths
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
     * Get current log filename based on logger channel.
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
     * Flush buffered data to log file.
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

        // Add to stream buffer
        $this->streams[$this->url] = $this->stream;
        $this->current = $channel;

        // Rotate the files
        // @codeCoverageIgnoreStart
        $this->rotate();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Log file rotation backup, only keep specified number.
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

        // Match all log files
        $baseFile = "{$this->url}.";
        $logFiles = glob("{$this->url}.*");

        // Sort the files by name to remove or rename the older ones
        // @codeCoverageIgnoreStart
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });
        /** @codeCoverageIgnoreEnd */

        // Remove the older files
        $offset = count($logFiles) - $this->backupCount + 1;
        if ($this->backupCount && $offset > 0) {
            // @codeCoverageIgnoreStart
            foreach (array_slice($logFiles, 0, $offset) as $logfile) {
                @unlink($logfile);
            }
            /** @codeCoverageIgnoreEnd */
            $logFiles = array_slice($logFiles, $offset);
        }

        // Rename the older files
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
