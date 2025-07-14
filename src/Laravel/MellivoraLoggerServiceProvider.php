<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mellivora\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Mellivora Logger Service Provider for Laravel.
 *
 * Provides Laravel framework integration support, including:
 * - Automatic configuration loading
 * - Singleton service registration
 * - Configuration publishing
 * - Helper function registration
 */
class MellivoraLoggerServiceProvider extends ServiceProvider
{
    /**
     * Whether to defer loading the service provider.
     */
    protected bool $defer = false;

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/mellivora-logger.php',
            'mellivora-logger',
        );

        $this->registerLoggerFactory();
        $this->registerLoggerAliases();
    }

    /**
     * Boot services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/mellivora-logger.php' => config_path('mellivora-logger.php'),
        ], 'mellivora-logger-config');

        $this->loadHelpers();
    }

    /**
     * Get the services provided by the service provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            LoggerFactory::class,
            'mellivora.logger.factory',
            'mellivora.logger',
            'mellivora.logger.channel',
        ];
    }

    /**
     * Register LoggerFactory service.
     */
    protected function registerLoggerFactory(): void
    {
        $this->app->singleton(LoggerFactory::class, function (Application $app): LoggerFactory {
            $config = $app['config']['mellivora-logger'] ?? [];

            // Set project root directory
            LoggerFactory::setRootPath(base_path());

            return LoggerFactory::build($config);
        });

        $this->app->alias(LoggerFactory::class, 'mellivora.logger.factory');
    }

    /**
     * Register Logger aliases.
     */
    protected function registerLoggerAliases(): void
    {
        // Register default Logger
        $this->app->bind('mellivora.logger', function (Application $app): LoggerInterface {
            return $app[LoggerFactory::class]->get();
        });

        // Register named Logger factory method
        $this->app->bind('mellivora.logger.channel', function (Application $app) {
            return function (string $channel): LoggerInterface {
                return $app[LoggerFactory::class]->get($channel);
            };
        });
    }

    /**
     * Load helper functions.
     */
    protected function loadHelpers(): void
    {
        if (!function_exists('mlog')) {
            require_once __DIR__ . '/helpers.php';
        }
    }
}
