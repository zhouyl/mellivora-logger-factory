<?php

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;

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
        $this->assertSame(LoggerFactory::getRootPath(), dirname(__DIR__));
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
}
