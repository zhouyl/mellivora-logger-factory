<?php

namespace Mellivora\Logger\Handler;

use Mellivora\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * 这个 handler 可以支持根据日志 channel 动态调整存储的文件名
 * 并支持日志缓冲，尤其适用常驻进程及脚本模式运行
 * 同时提供了日志分片备份服务
 */
class NamedRotatingFileHandler extends StreamHandler
{
    protected $filename;
    protected $maxBytes;
    protected $backupCount;
    protected $bufferSize;
    protected $dateFormat;

    private $streams     = [];
    private $buffers     = [];
    private $current;
    private $dirCreated;
    private $initialized = false;

    /**
     * @param string   $filename       日志文件名，可使用 %channel% 来动态生成文件名
     * @param int      $maxBytes       单个日志文件最大尺寸，默认值 100000000 (100mb)，0为不限制
     * @param int      $backupCount    保留的日志备份文件数量，默认值 10，0为不保留备份
     * @param int      $bufferSize     缓冲区日志数大小，默认值 0，不开启
     * @param string   $dateFormat     备份日志文件的日期格式
     * @param int      $level
     * @param bool     $bubble
     * @param null|int $filePermission
     * @param bool     $useLocking
     */
    public function __construct(
        $filename,
        $maxBytes=100000000,
        $backupCount=10,
        $bufferSize=0,
        $dateFormat='Y-m-d',
        $level = Logger::DEBUG,
        $bubble = true,
        $filePermission = null,
        $useLocking = false
    ) {
        if (! (substr($filename, 0, 1) === '/' || substr($filename, 0, 7) === 'file://')) {
            $filename = LoggerFactory::getRootPath() . '/' . $filename;
        }

        $this->filename    = $filename;
        $this->maxBytes    = (int) $maxBytes;
        $this->backupCount = (int) $backupCount;
        $this->bufferSize  = (int) $bufferSize;
        $this->dateFormat  = $dateFormat;

        parent::__construct(
            $this->getFilename(),
            $level,
            $bubble,
            $filePermission,
            $useLocking
        );
    }

    /**
     * 根据 logger channel 获取当前日志文件名
     *
     * @param mixed $channel
     *
     * @throws \UnexpectedValueException
     *
     * @return string
     */
    public function getFilename($channel='')
    {
        if ($this->dirCreated) {
            $logPath = dirname($this->filename);

            if (! is_dir($logPath)) {
                @mkdir($logPath, 0777, true);
            }

            if (! is_writable($logPath)) {
                throw new \UnexpectedValueException(
                    'Unable to write to the log path: ' . $logPath
                );
            }

            $this->dirCreated = true;
        }

        return str_replace(
            ['%date%', '%channel%'],
            [date($this->dateFormat), $channel],
            $this->filename
        );
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (! $this->isHandling($record)) {
            return false;
        }

        if (! $this->initialized) {
            register_shutdown_function([$this, 'close']);
            $this->initialized = true;
        }

        $record  = $this->processRecord($record);
        $channel = $record['channel'];

        $record['formatted'] = $this->getFormatter()->format($record);

        if ($this->bufferSize) {
            $this->buffers[$channel][] = $record;
            if (count($this->buffers[$channel]) > $this->bufferSize) {
                $this->flush($channel);
            }
        } else {
            $this->write($record);
        }

        return false === $this->bubble;
    }

    /**
     * 刷新缓冲数据到日志文件
     *
     * @param null|string $channel
     */
    public function flush($channel = null)
    {
        if ($channel === null) {
            array_map([$this, 'flush'], array_keys($this->buffers));

            return;
        }

        if ($this->buffers[$channel]) {
            foreach ($this->buffers[$channel] as $record) {
                $this->write($record);
            }

            $this->buffers[$channel] = [];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $channel   = $record['channel'];

        if ($channel !== $this->current || ! $this->stream) {
            $this->url = $this->getFilename($channel);

            if (isset($this->streams[$this->url])) {
                $this->stream = $this->streams[$this->url];
            } else {
                $this->stream = null;
            }
        }

        parent::write($record);

        // add to stream buffer
        $this->streams[$this->url] = $this->stream;
        $this->current             = $channel;

        // rotates the files
        $this->rotate();
    }

    /**
     * 日志分片备份，仅保留指定数量
     */
    protected function rotate()
    {
        if (! $this->maxBytes) {
            return;
        }

        $size = fstat($this->stream)['size'];
        if ($size < $this->maxBytes) {
            return;
        }

        fclose($this->stream);
        unset($this->streams[$this->url]);
        $this->stream = null;

        // matching all log files
        $fileInfo = pathinfo($this->url);
        $baseFile = "{$this->url}.";
        $logFiles = glob("{$this->url}.*");

        // sorting the files by name to remove or rename the older ones
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });

        // remove the older files
        $offset = count($logFiles) - $this->backupCount + 1;
        if ($this->backupCount && $offset > 0) {
            foreach (array_slice($logFiles, 0, $offset) as $logfile) {
                @unlink($logfile);
            }
            $logFiles = array_slice($logFiles, $offset);
        }

        // rename the older files
        for ($i = count($logFiles); $i > 0; --$i) {
            $this->rename($baseFile . $i, $baseFile . ($i + 1));
        }

        // rename current log file
        $this->rename($this->url, $baseFile . '1');
    }

    /**
     * @param string $source
     * @param string $target
     */
    protected function rename($source, $target)
    {
        if (is_file($source)) {
            if (is_file($target)) {
                @unlink($target);
            }
            @rename($source, $target);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->bufferSize && $this->flush();

        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->url     = null;
        $this->stream  = null;
        $this->streams = [];
    }
}
