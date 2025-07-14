<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use Mellivora\Logger\Laravel\Commands\MellivoraLoggerTestCommand;
use Mellivora\Logger\Laravel\Facades\MLog;
use Mellivora\Logger\Laravel\MellivoraLoggerServiceProvider;
use Mellivora\Logger\Laravel\Middleware\LogRequestMiddleware;
use Mellivora\Logger\LoggerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Laravel integration test suite.
 *
 * Tests Laravel-specific functionality including:
 * - Service Provider registration
 * - Facade functionality
 * - Middleware operations
 * - Helper functions
 * - Laravel 12 compatibility
 */
class LaravelIntegrationTest extends TestCase
{
    private Container $app;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mellivora_laravel_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);

        // Create Laravel container
        $this->app = new Container();
        Container::setInstance($this->app);

        // Set up Facade
        Facade::setFacadeApplication($this->app);

        // Mock config service
        $this->app->singleton('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return $default;
                }
                public function set($key, $value) {
                    // Mock implementation
                }
            };
        });

        // Register the service provider
        $provider = new MellivoraLoggerServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        Container::setInstance(null);
        Facade::clearResolvedInstances();
    }

    public function testServiceProviderRegistration(): void
    {
        $this->assertTrue($this->app->bound('mellivora.logger.factory'));
        $this->assertTrue($this->app->bound('mellivora.logger'));

        $factory = $this->app->make('mellivora.logger.factory');
        $this->assertInstanceOf(LoggerFactory::class, $factory);
    }

    public function testFacadeBasicLogging(): void
    {
        // Test basic logging methods
        $this->assertTrue(MLog::debug('Debug message'));
        $this->assertTrue(MLog::info('Info message'));
        $this->assertTrue(MLog::warning('Warning message'));
        $this->assertTrue(MLog::error('Error message'));
        $this->assertTrue(MLog::critical('Critical message'));
    }

    public function testFacadeLogWithChannel(): void
    {
        $result = MLog::logWith('api', 'info', 'API test message', ['user_id' => 123]);
        $this->assertTrue($result);
    }

    public function testFacadeExceptionLogging(): void
    {
        $exception = new \Exception('Test exception');
        $result = MLog::exception($exception);
        $this->assertTrue($result);

        // Test with custom level
        $result = MLog::exception($exception, 'critical');
        $this->assertTrue($result);
    }

    public function testFacadeGetLogger(): void
    {
        $logger = MLog::logger();
        $this->assertNotNull($logger);

        $apiLogger = MLog::logger('api');
        $this->assertNotNull($apiLogger);
    }

    public function testHelperFunctions(): void
    {
        // Test if helper functions are loaded
        $this->assertTrue(function_exists('mlog'));
        $this->assertTrue(function_exists('mlog_with'));
        $this->assertTrue(function_exists('mlog_debug'));
        $this->assertTrue(function_exists('mlog_info'));
        $this->assertTrue(function_exists('mlog_warning'));
        $this->assertTrue(function_exists('mlog_error'));
        $this->assertTrue(function_exists('mlog_critical'));
        $this->assertTrue(function_exists('mlog_exception'));
    }

    public function testHelperFunctionBasicUsage(): void
    {
        $this->assertTrue(mlog('info', 'Helper test message'));
        $this->assertTrue(mlog_info('Info helper test'));
        $this->assertTrue(mlog_debug('Debug helper test'));
        $this->assertTrue(mlog_warning('Warning helper test'));
        $this->assertTrue(mlog_error('Error helper test'));
        $this->assertTrue(mlog_critical('Critical helper test'));
    }

    public function testHelperFunctionWithChannel(): void
    {
        $result = mlog_with('api', 'info', 'Channel helper test', ['data' => 'test']);
        $this->assertTrue($result);
    }

    public function testHelperFunctionExceptionLogging(): void
    {
        $exception = new \Exception('Helper exception test');
        $result = mlog_exception($exception);
        $this->assertTrue($result);

        $result = mlog_exception($exception, 'error');
        $this->assertTrue($result);
    }

    public function testMiddlewareBasicFunctionality(): void
    {
        $middleware = new LogRequestMiddleware();

        $request = Request::create('/test', 'GET');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('Test response', $result->getContent());
    }

    public function testMiddlewareWithCustomChannel(): void
    {
        $middleware = new LogRequestMiddleware();

        $request = Request::create('/api/test', 'POST', ['data' => 'test']);
        $response = new Response('API response');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next, 'api', 'debug');

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testMiddlewareWithDifferentHttpMethods(): void
    {
        $middleware = new LogRequestMiddleware();
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/test', $method);
            $response = new Response("Response for {$method}");

            $next = function ($req) use ($response) {
                return $response;
            };

            $result = $middleware->handle($request, $next);

            $this->assertInstanceOf(Response::class, $result);
            $this->assertEquals("Response for {$method}", $result->getContent());
        }
    }

    public function testServiceProviderProvidesCorrectServices(): void
    {
        $provider = new MellivoraLoggerServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertContains('mellivora.logger.factory', $provides);
        $this->assertContains('mellivora.logger', $provides);
    }

    public function testLaravel12Compatibility(): void
    {
        // Test that Laravel 12 components work correctly
        $this->assertTrue(class_exists(\Illuminate\Support\ServiceProvider::class));
        $this->assertTrue(class_exists(\Illuminate\Support\Facades\Facade::class));
        $this->assertTrue(class_exists(\Illuminate\Http\Request::class));
        $this->assertTrue(class_exists(\Illuminate\Container\Container::class));

        // Test version compatibility
        $version = $this->app->version() ?? '12.0.0'; // Default to 12.0.0 if not available
        $this->assertMatchesRegularExpression('/^(10\.|11\.|12\.)/', $version);
    }

    public function testFacadeWithComplexData(): void
    {
        $complexData = [
            'user' => ['id' => 123, 'name' => 'John Doe'],
            'request' => ['method' => 'POST', 'url' => '/api/test'],
            'metadata' => ['timestamp' => time(), 'version' => '1.0.0']
        ];

        $result = MLog::info('Complex data test', $complexData);
        $this->assertTrue($result);
    }

    public function testMiddlewareErrorHandling(): void
    {
        $middleware = new LogRequestMiddleware();

        $request = Request::create('/error-test', 'GET');

        $next = function ($req) {
            throw new \Exception('Middleware test exception');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Middleware test exception');

        $middleware->handle($request, $next);
    }

    public function testHelperFunctionErrorHandling(): void
    {
        // Test with invalid log level
        $result = mlog('invalid_level', 'Test message');
        $this->assertFalse($result);
    }

    public function testFacadeErrorHandling(): void
    {
        // Test facade with invalid parameters
        $result = MLog::logWith('', '', '');
        $this->assertFalse($result);
    }

    public function testServiceProviderDeferred(): void
    {
        $provider = new MellivoraLoggerServiceProvider($this->app);

        // Service provider should not be deferred for immediate availability
        $reflection = new \ReflectionClass($provider);
        if ($reflection->hasProperty('defer')) {
            $deferProperty = $reflection->getProperty('defer');
            $deferProperty->setAccessible(true);
            $this->assertFalse($deferProperty->getValue($provider));
        }
    }

    public function testLaravelCommand(): void
    {
        $command = new MellivoraLoggerTestCommand();

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Testing Mellivora Logger', $outputContent);
    }

    public function testLaravelCommandWithOptions(): void
    {
        $command = new MellivoraLoggerTestCommand();

        $input = new ArrayInput([
            '--channel' => 'api',
            '--level' => 'debug',
            '--message' => 'Custom test message'
        ]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Custom test message', $outputContent);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
