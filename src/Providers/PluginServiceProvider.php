<?php

namespace Siaoynli\Plugins\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Siaoynli\Plugins\PluginManager;

/**
 * Plugin Service Provider
 *
 * 用于注册和启动插件系统
 */
class PluginServiceProvider extends ServiceProvider
{
  /**
   * 注册服务
   * 这里只注册单例，不要加载插件
   */
  public function register(): void
  {
    \Log::info('========== PluginServiceProvider::register() ==========');

    try {
      // 注册插件管理器为单例，但不在这里加载插件
      $this->app->singleton(PluginManager::class, function ($app) {
        \Log::info('Creating PluginManager singleton');
        return new PluginManager();
      });

      // 也可以使用短名称访问
      $this->app->alias(PluginManager::class, 'plugin-manager');

      \Log::info('PluginManager singleton registered');
    } catch (\Exception $e) {
      \Log::error('Error in PluginServiceProvider::register(): ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ]);
    }
  }

  /**
   * 启动服务
   * 在这里加载和启动插件
   */
  public function boot(): void
  {
    \Log::info('========== PluginServiceProvider::boot() ==========');

    try {
      $manager = $this->app->make(PluginManager::class);

      // 加载插件 - 在 boot 阶段，autoloader 已经完全初始化
      \Log::info('Loading plugins...');
      $manager->loadPlugins();

      $pluginCount = count($manager->getPlugins());
      \Log::info("Loaded {$pluginCount} plugins");

      // 启动所有插件
      \Log::info('Booting plugins...');
      $manager->bootPlugins();

      // 注册路由
      \Log::info('Registering plugin routes...');
      $manager->registerRoutes();

      \Log::info('All plugin routes registered');

      // 注册 Artisan 命令 - 不需要发布，直接生效
      $this->registerCommands();

      // 发布资源 - 只在运行 console 命令时
      if ($this->app->runningInConsole()) {
        try {
          \Log::info('Publishing plugin assets...');
          $manager->publishAssets();
          \Log::info('Plugin assets published');
        } catch (\Exception $e) {
          \Log::warning('Error publishing assets: ' . $e->getMessage());
        }
      }
    } catch (\Exception $e) {
      \Log::error('Error in PluginServiceProvider::boot(): ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ]);
    }

    $this->publishConfig();

    \Log::info('========== PluginServiceProvider::boot() Completed ==========');
  }

  /**
   * 注册 Artisan 命令
   * 命令不需要发布，直接生效
   */
  protected function registerCommands(): void
  {
    if ($this->app->runningInConsole()) {
      // 直接注册插件相关的 Artisan 命令
      // 这些命令会被自动加载，不需要 vendor:publish
      $this->commands([
        \Siaoynli\Plugins\Console\Commands\PluginListCommand::class,
        \Siaoynli\Plugins\Console\Commands\PluginPublishCommand::class,
      ]);

      \Log::debug('Plugin commands registered successfully');
    }
  }


  /**
   * 发布配置
   */
  protected function publishConfig(): void
  {
    if (File::isFile(__DIR__ . '/../../config/plugin.php')) {
      $this->publishes([
        __DIR__ . '/../../config/plugin.php' =>  config_path('app-plugins.php'),
      ], 'phone-auth-config');
    }
  }
}
