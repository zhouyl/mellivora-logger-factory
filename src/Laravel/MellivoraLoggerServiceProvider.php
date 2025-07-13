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
 * 提供 Laravel 框架集成支持，包括：
 * - 自动配置加载
 * - 单例服务注册
 * - 配置发布
 * - 便捷函数注册
 */
class MellivoraLoggerServiceProvider extends ServiceProvider
{
    /**
     * 是否延迟加载服务提供者.
     */
    protected bool $defer = false;

    /**
     * 注册服务
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
     * 启动服务
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/mellivora-logger.php' => config_path('mellivora-logger.php'),
        ], 'mellivora-logger-config');

        $this->loadHelpers();
    }

    /**
     * 获取服务提供者提供的服务
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
     * 注册 LoggerFactory 服务
     */
    protected function registerLoggerFactory(): void
    {
        $this->app->singleton(LoggerFactory::class, function (Application $app): LoggerFactory {
            $config = $app['config']['mellivora-logger'] ?? [];

            // 设置项目根目录
            LoggerFactory::setRootPath(base_path());

            return LoggerFactory::build($config);
        });

        $this->app->alias(LoggerFactory::class, 'mellivora.logger.factory');
    }

    /**
     * 注册 Logger 别名.
     */
    protected function registerLoggerAliases(): void
    {
        // 注册默认 Logger
        $this->app->bind('mellivora.logger', function (Application $app): LoggerInterface {
            return $app[LoggerFactory::class]->get();
        });

        // 注册命名 Logger 工厂方法
        $this->app->bind('mellivora.logger.channel', function (Application $app) {
            return function (string $channel): LoggerInterface {
                return $app[LoggerFactory::class]->get($channel);
            };
        });
    }

    /**
     * 加载辅助函数.
     */
    protected function loadHelpers(): void
    {
        if (!function_exists('mlog')) {
            require_once __DIR__ . '/helpers.php';
        }
    }
}
