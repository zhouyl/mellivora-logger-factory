<?php

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;
use Monolog\Handler\NullHandler;
use Psr\Log\NullLogger;

/**
 * @internal
 */
class LoggerFactoryTest extends TestCase
{
    protected $factory;

    protected function setUp()
    {
        parent::setUp();

        LoggerFactory::setRootPath(dirname(__DIR__));

        $this->factory = LoggerFactory::buildWith(
            $this->withRootPath('config/logger.php')
        );
    }

    protected function withRootPath($filename)
    {
        return realpath(LoggerFactory::getRootPath() . '/' . $filename);
    }

    public function testRootPath()
    {
        $this->assertSame(dirname(__DIR__), LoggerFactory::getRootPath());
        $this->assertSame(
            __FILE__,
            $this->withRootPath('/tests/' . basename(__FILE__))
        );
    }

    public function testBuild()
    {
        $pattern = LoggerFactory::getRootPath() . '/config/*';
        foreach (glob($pattern) as $file) {
            $factory = LoggerFactory::buildWith($file);
            $this->assertTrue($factory->exists($factory->getDefault()));
        }
    }

    public function testDefault()
    {
        $this->factory->setDefault('cli');
        $this->assertSame('cli', $this->factory->getDefault());

        $this->expectException(\RuntimeException::class);
        $this->factory->setDefault('foo');
    }

    public function testAccessor()
    {
        $logger = new NullLogger;
        $this->factory->add('null', $logger);
        $this->assertTrue($this->factory->exists('null'));

        $this->assertSame($logger, $this->factory->get('null'));
        $this->assertSame($logger, $this->factory['null']);

        $this->assertFalse($this->factory->exists('not_exist_logger'));
        $this->assertFalse(isset($this->factory['not_exist_logger']));

        unset($this->factory['null']);
        $this->assertTrue($this->factory->exists('null'));

        $this->factory->release();
        $this->assertFalse($this->factory->exists('null'));

        $this->factory['null'] = $logger;
        $this->assertTrue($this->factory->exists('null'));

        $this->assertInstanceOf(
            NullHandler::class,
            $this->factory->make('make_null')->popHandler()
        );
    }
}
