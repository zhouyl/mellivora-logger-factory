<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Mellivora\Logger\LoggerFactory;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @internal
 */
class LoggerFactoryTest extends TestCase
{
    protected LoggerFactory $factory;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/mellivora_logger_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);

        LoggerFactory::setRootPath(dirname(__DIR__));

        $this->factory = LoggerFactory::buildWith(
            $this->withRootPath('config/logger.php'),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testRootPath(): void
    {
        $this->assertSame(dirname(__DIR__), LoggerFactory::getRootPath());
        $this->assertSame(
            __FILE__,
            $this->withRootPath('/tests/' . basename(__FILE__)),
        );

        // Test setting and getting root path
        $originalPath = LoggerFactory::getRootPath();
        LoggerFactory::setRootPath('/test/path');
        $this->assertEquals('/test/path', LoggerFactory::getRootPath());
        LoggerFactory::setRootPath($originalPath);
    }

    public function testBuild(): void
    {
        $configFile = LoggerFactory::getRootPath() . '/config/logger.php';
        $factory = LoggerFactory::buildWith($configFile);
        $this->assertTrue($factory->exists($factory->getDefault()));

        // Test build with array config
        $config = [
            'default' => 'test',
            'handlers' => [
                'null' => [
                    'class' => 'Monolog\Handler\NullHandler',
                    'params' => [],
                ],
            ],
            'loggers' => [
                'test' => ['null'],
            ],
        ];

        $factory2 = LoggerFactory::build($config);
        $this->assertInstanceOf(LoggerFactory::class, $factory2);
        $this->assertEquals('test', $factory2->getDefault());
    }

    public function testBuildWithNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');

        LoggerFactory::buildWith('/non/existent/file.php');
    }

    public function testDefault(): void
    {
        $this->factory->setDefault('cli');
        $this->assertSame('cli', $this->factory->getDefault());

        $this->expectException(\RuntimeException::class);
        $this->factory->setDefault('foo');
    }

    public function testAccessor(): void
    {
        $logger = new NullLogger();
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
            $this->factory->make('make_null')->popHandler(),
        );
    }

    public function testMakeWithDifferentHandlerTypes(): void
    {
        // Test make with string handler
        $logger1 = $this->factory->make('test1', 'cli');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger1);

        // Test make with array of handlers
        $logger2 = $this->factory->make('test2', ['cli', 'file']);
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger2);

        // Test make with null (should get NullHandler)
        $logger3 = $this->factory->make('test3', null);
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger3);

        // Test make with empty array
        $logger4 = $this->factory->make('test4', []);
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $logger4);
    }

    public function testGetWithFallback(): void
    {
        // Test getting non-existent logger (should fallback to default)
        $logger = $this->factory->get('nonexistent');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);

        // Test getting with null
        $defaultLogger = $this->factory->get(null);
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $defaultLogger);

        // Test getting with empty string
        $emptyLogger = $this->factory->get('');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $emptyLogger);
    }

    public function testComplexConfiguration(): void
    {
        $configFile = $this->tempDir . '/complex_config.php';

        $config = [
            'default' => 'app',
            'formatters' => [
                'line' => [
                    'class' => 'Monolog\Formatter\LineFormatter',
                    'params' => [
                        'format' => "[%datetime%] %level_name%: %message%\n",
                    ],
                ],
            ],
            'processors' => [
                'memory' => [
                    'class' => 'Mellivora\Logger\Processor\MemoryProcessor',
                    'params' => [
                        'level' => Level::Debug,
                        'realUsage' => true,
                        'useFormatting' => true,
                    ],
                ],
            ],
            'handlers' => [
                'test' => [
                    'class' => 'Monolog\Handler\TestHandler',
                    'params' => [
                        'level' => Level::Debug,
                    ],
                    'formatter' => 'line',
                    'processors' => ['memory'],
                ],
            ],
            'loggers' => [
                'app' => ['test'],
                'api' => ['test'],
            ],
        ];

        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

        $factory = LoggerFactory::buildWith($configFile);

        $appLogger = $factory->get('app');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $appLogger);

        $apiLogger = $factory->get('api');
        $this->assertInstanceOf(\Mellivora\Logger\Logger::class, $apiLogger);
    }

    public function testNewInstanceWithOptionReflection(): void
    {
        $factory = new LoggerFactory();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with simple class
        $option = [
            'class' => 'Monolog\Handler\NullHandler',
            'params' => [],
        ];
        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\NullHandler', $instance);

        // Test missing class parameter
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing the 'class' parameter");
        $method->invoke($factory, []);
    }

    public function testNewInstanceWithNonExistentClass(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' not found");

        $method->invoke($factory, ['class' => 'NonExistentClass']);
    }

    public function testNewInstanceWithLevelConversion(): void
    {
        $factory = new LoggerFactory();

        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('newInstanceWithOption');
        $method->setAccessible(true);

        // Test with string level that gets converted to Level enum
        $option = [
            'class' => 'Monolog\Handler\TestHandler',
            'params' => [
                'level' => 'debug', // String level
                'bubble' => true,
            ],
        ];

        $instance = $method->invoke($factory, $option);
        $this->assertInstanceOf('Monolog\Handler\TestHandler', $instance);
    }

    public function testConstructorWithoutDefault(): void
    {
        $factory = new LoggerFactory([
            'loggers' => [
                'first' => [],
                'second' => [],
            ],
        ]);

        // Should use first logger as default
        $this->assertEquals('first', $factory->getDefault());
    }

    public function testConstructorWithEmptyLoggers(): void
    {
        $factory = new LoggerFactory([]);

        // Should use 'default' as default
        $this->assertEquals('default', $factory->getDefault());
    }

    protected function withRootPath(string $filename): string
    {
        return realpath(LoggerFactory::getRootPath() . '/' . $filename);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
