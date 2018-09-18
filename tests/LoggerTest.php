<?php

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

/**
 * @internal
 */
class LoggerTest extends TestCase
{
    protected $logger;
    protected $handler;
    protected $stream;
    protected $lastLogString;

    protected function setUp()
    {
        parent::setUp();
        $this->buildLogger();
    }

    protected function buildLogger()
    {
        $this->logger  = new Logger('testcase');
        $this->stream  = fopen('php://memory', 'a+');
        $this->handler = new StreamHandler($this->stream);

        $this->handler->setFormatter(new JsonFormatter);
        $this->logger->pushHandler($this->handler);
    }

    protected function lastLogString()
    {
        fseek($this->stream, 0);
        $log = fgets($this->stream);
        ftruncate($this->stream, 0);
        fseek($this->stream, 0);

        $this->lastLogString =$log ? trim($log) : false;

        return $this->lastLogString;
    }

    protected function lastLogJson()
    {
        return json_decode($this->lastLogString);
    }

    public function testLevel()
    {
        $this->logger->setLevel(Logger::INFO);
        $this->assertSame(Logger::INFO, $this->logger->getLevel());

        $this->logger->warning('warning');
        $this->assertStringContains($this->lastLogString(), 'warning');

        $this->logger->debug('debug');
        $this->assertStringNotContains($this->lastLogString(), 'debug');
    }

    public function testAddException()
    {
        try {
            throw new \RuntimeException('test excetpion');
        } catch (\Exception $ex) {
            $this->logger->addException($ex);
        }
        $this->assertStringContains($this->lastLogString(), 'RuntimeException');
    }

    public function testHandler()
    {
        $handler = new NullHandler;
        $this->logger->pushHandler($handler);

        $this->assertSame($handler, $this->logger->getHandler(NullHandler::class));

        $this->logger->removeHandler(NullHandler::class);
        $this->assertFalse($this->logger->getHandler(NullHandler::class));
    }

    public function testFilter()
    {
        $this->logger->pushFilter(function ($level, $message, $context) {
            return strpos($message, 'deny') === false;
        });
        $this->assertSame(1, count($this->logger->getFilters()));

        $this->logger->info('is deny msg');
        $this->assertStringNotContains($this->lastLogString(), 'deny');

        $this->logger->popFilter();
        $this->assertSame(0, count($this->logger->getFilters()));

        $this->logger->info('is deny msg');
        $this->assertStringContains($this->lastLogString(), 'deny');
    }

    public function testToString()
    {
        $this->assertSame('Logger(testcase)', $this->logger->__toString());
    }
}
