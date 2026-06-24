<?php

namespace Siaoynli\Plugins\Publisher;

use Illuminate\Support\ServiceProvider;
use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Plugin Publisher
 *
 * 使用 Laravel 原生的 publishes() 声明式注册可发布资源。
 * 资源不会在 boot 时自动拷贝，用户通过 vendor:publish 命令手动发布。
 *
 * 用法：
 *   php artisan vendor:publish --tag=vendor-package-config
 *   php artisan vendor:publish --tag=vendor-package-migrations
 *   php artisan vendor:publish --tag=vendor-package-views
 *   php artisan vendor:publish --tag=vendor-package-assets
 */
class PluginPublisher
{
    /**
     * 为指定插件声明式注册可发布资源
     */
    public function register(ServiceProvider $provider, PluginInterface $plugin): void
    {
        // 通过反射获取 basePath（PluginInterface 不再强制要求此方法）
        if (!method_exists($plugin, 'getBasePath')) {
            return;
        }

        $basePath = $plugin->getBasePath();
        $tag = $this->getTag($plugin->getName());

        // 迁移文件
        $this->registerMigrations($provider, $basePath, $tag);

        // 配置文件
        $this->registerConfig($provider, $basePath, $tag, $plugin->getName());

        // 视图文件
        $this->registerViews($provider, $basePath, $tag, $plugin->getName());

        // 静态资源（CSS, JS, 图片等）
        $this->registerAssets($provider, $basePath, $tag, $plugin->getName());
    }

    /**
     * 注册迁移文件
     */
    protected function registerMigrations(ServiceProvider $provider, string $basePath, string $tag): void
    {
        $path = $basePath . '/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        $provider->publishes([
            $path => database_path('migrations'),
        ], $tag . '-migrations');
    }

    /**
     * 注册配置文件
     */
    protected function registerConfig(ServiceProvider $provider, string $basePath, string $tag, string $pluginName): void
    {
        $configFile = $basePath . '/config/plugin.php';

        if (!file_exists($configFile)) {
            return;
        }

        $slugName = str_replace('/', '-', $pluginName);
        $provider->publishes([
            $configFile => config_path("plugins/{$slugName}.php"),
        ], $tag . '-config');
    }

    /**
     * 注册视图文件
     */
    protected function registerViews(ServiceProvider $provider, string $basePath, string $tag, string $pluginName): void
    {
        $viewsPath = $basePath . '/resources/views';

        if (!is_dir($viewsPath)) {
            return;
        }

        $slugName = str_replace('/', '-', $pluginName);

        // 注册视图命名空间，使插件可以直接使用 view('pluginName::view')
        $provider->loadViewsFrom($viewsPath, $slugName);

        $provider->publishes([
            $viewsPath => resource_path("views/vendor/{$slugName}"),
        ], $tag . '-views');
    }

    /**
     * 注册静态资源文件
     */
    protected function registerAssets(ServiceProvider $provider, string $basePath, string $tag, string $pluginName): void
    {
        $assetsPath = $basePath . '/resources/assets';

        if (!is_dir($assetsPath)) {
            return;
        }

        $slugName = str_replace('/', '-', $pluginName);

        $provider->publishes([
            $assetsPath => public_path("plugins/{$slugName}"),
        ], $tag . '-assets');
    }

    /**
     * 生成发布标签名
     * 例：siaoynli/phone-auth-plugin → siaoynli-phone-auth-plugin
     */
    protected function getTag(string $pluginName): string
    {
        return str_replace('/', '-', $pluginName);
    }
}
