<?php

namespace Siaoynli\Plugins\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Siaoynli\Plugins\Contracts\PluginInterface;
use Siaoynli\Plugins\Discovery\PluginDiscovery;
use Siaoynli\Plugins\Events\PluginBooted;
use Siaoynli\Plugins\Events\PluginRegistered;
use Siaoynli\Plugins\PluginManifest;
use Siaoynli\Plugins\PluginManager;
use Siaoynli\Plugins\Publisher\PluginPublisher;
use Siaoynli\Plugins\Registry\PluginRegistry;

/**
 * Plugin Service Provider
 *
 * 插件系统的 Laravel 入口。
 *
 * 生命周期：
 * - register(): 绑定单例 + 合并自身配置
 * - boot(): 发现插件 → 注册(register) → 启动(boot) → 路由 → 发布注册 → 命令
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * 注册服务 — 仅绑定容器，不加载插件
     */
    public function register(): void
    {
        // 合并本包自身的配置
        $this->mergeConfigFrom(__DIR__ . '/../../config/plugin.php', 'plugins');

        // 绑定单例
        $this->app->singleton(PluginManifest::class, function ($app) {
            $path = config('plugins.cache.path', $app->bootstrapPath('cache/plugins.php'));
            return new PluginManifest($path);
        });

        $this->app->singleton(PluginDiscovery::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(PluginPublisher::class);

        // 向后兼容：PluginManager 作为 Registry 的门面
        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager($app->make(PluginRegistry::class));
        });
        $this->app->alias(PluginManager::class, 'plugin-manager');
    }

    /**
     * 启动服务 — 按正确时序加载插件
     */
    public function boot(): void
    {
        // 检查插件系统是否启用
        if (!config('plugins.enabled', true)) {
            return;
        }

        try {
            $this->bootPlugins();
        } catch (\Exception $e) {
            \Log::error('Plugin system boot failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // 注册命令（始终注册，即使插件系统禁用）
        $this->registerCommands();

        // 注册配置文件发布
        $this->publishConfig();
    }

    /**
     * 启动插件系统
     */
    protected function bootPlugins(): void
    {
        $discovery = $this->app->make(PluginDiscovery::class);
        $registry = $this->app->make(PluginRegistry::class);
        $publisher = $this->app->make(PluginPublisher::class);
        $manifest = $this->app->make(PluginManifest::class);

        // 1. 发现插件（有缓存则跳过扫描）
        $pluginData = $discovery->discover($manifest);

        // 2. 实例化并注册到 Registry
        foreach ($pluginData as $packageName => $data) {
            $pluginClass = $data['class'] ?? null;

            if (!$pluginClass || !class_exists($pluginClass)) {
                \Log::warning("Plugin class not found: {$pluginClass} ({$packageName})");
                continue;
            }

            /** @var PluginInterface $plugin */
            $plugin = new $pluginClass();

            if (!$plugin->isEnabled()) {
                \Log::debug("Plugin disabled, skipping: {$packageName}");
                continue;
            }

            $registry->register($packageName, $plugin);
            event(new PluginRegistered($plugin));
        }

        // 3. 调用 register()（服务绑定阶段）
        foreach ($registry->all() as $name => $plugin) {
            try {
                $plugin->register();
            } catch (\Exception $e) {
                \Log::error("Error in plugin register(): {$name} - " . $e->getMessage());
            }
        }

        // 4. 调用 boot() + registerRoutes()（启动阶段）
        foreach ($registry->all() as $name => $plugin) {
            try {
                $plugin->boot();
                $plugin->registerRoutes();
                event(new PluginBooted($plugin));
            } catch (\Exception $e) {
                \Log::error("Error in plugin boot(): {$name} - " . $e->getMessage());
            }
        }

        // 5. 声明式注册可发布资源（不拷贝文件）
        foreach ($registry->all() as $plugin) {
            $publisher->register($this, $plugin);
        }

        \Log::debug('Plugin system booted', ['count' => $registry->count()]);
    }

    /**
     * 注册 Artisan 命令
     */
    protected function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            \Siaoynli\Plugins\Console\Commands\PluginListCommand::class,
            \Siaoynli\Plugins\Console\Commands\PluginPublishCommand::class,
            \Siaoynli\Plugins\Console\Commands\PluginCacheCommand::class,
            \Siaoynli\Plugins\Console\Commands\PluginClearCommand::class,
        ]);
    }

    /**
     * 发布本包的配置文件
     */
    protected function publishConfig(): void
    {
        $configPath = __DIR__ . '/../../config/plugin.php';

        if (File::isFile($configPath)) {
            $this->publishes([
                $configPath => config_path('plugins.php'),
            ], 'laravel-plugins-config');
        }
    }
}
