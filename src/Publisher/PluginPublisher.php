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
     * 收集插件的可发布资源路径和视图命名空间
     *
     * 返回结构化数组，由 ServiceProvider 调用 publishes() / loadViewsFrom()。
     * PluginPublisher 本身不是 ServiceProvider 子类，无法直接调用这些 protected 方法。
     *
     * @return array{publishes: array<string, array<string, string>>, views: array<string, string>}
     */
    public function register(ServiceProvider $provider, PluginInterface $plugin): array
    {
        // 通过反射获取 basePath（PluginInterface 不再强制要求此方法）
        if (!method_exists($plugin, 'getBasePath')) {
            return ['publishes' => [], 'views' => []];
        }

        $basePath = $plugin->getBasePath();
        $tag = $this->getTag($plugin->getName());

        $publishes = [];
        $views = [];

        // 迁移文件
        $publishes = array_merge($publishes, $this->collectMigrations($basePath, $tag));

        // 配置文件
        $publishes = array_merge($publishes, $this->collectConfig($basePath, $tag, $plugin->getName()));

        // 视图文件
        $viewResult = $this->collectViews($basePath, $tag, $plugin->getName());
        $publishes = array_merge($publishes, $viewResult['publishes']);
        $views = array_merge($views, $viewResult['views']);

        // 静态资源（CSS, JS, 图片等）
        $publishes = array_merge($publishes, $this->collectAssets($basePath, $tag, $plugin->getName()));

        return ['publishes' => $publishes, 'views' => $views];
    }

    /**
     * 收集迁移文件发布路径
     */
    protected function collectMigrations(string $basePath, string $tag): array
    {
        $path = $basePath . '/database/migrations';

        if (!is_dir($path)) {
            return [];
        }

        return [
            $tag . '-migrations' => [
                $path => database_path('migrations'),
            ],
        ];
    }

    /**
     * 收集配置文件发布路径
     */
    protected function collectConfig(string $basePath, string $tag, string $pluginName): array
    {
        $configFile = $basePath . '/config/plugin.php';

        if (!file_exists($configFile)) {
            return [];
        }

        $slugName = str_replace('/', '-', $pluginName);

        return [
            $tag . '-config' => [
                $configFile => config_path("plugins/{$slugName}.php"),
            ],
        ];
    }

    /**
     * 收集视图文件发布路径和命名空间
     */
    protected function collectViews(string $basePath, string $tag, string $pluginName): array
    {
        $viewsPath = $basePath . '/resources/views';

        if (!is_dir($viewsPath)) {
            return ['publishes' => [], 'views' => []];
        }

        $slugName = str_replace('/', '-', $pluginName);

        return [
            'publishes' => [
                $tag . '-views' => [
                    $viewsPath => resource_path("views/vendor/{$slugName}"),
                ],
            ],
            'views' => [
                $slugName => $viewsPath,
            ],
        ];
    }

    /**
     * 收集静态资源发布路径
     */
    protected function collectAssets(string $basePath, string $tag, string $pluginName): array
    {
        $assetsPath = $basePath . '/resources/assets';

        if (!is_dir($assetsPath)) {
            return [];
        }

        $slugName = str_replace('/', '-', $pluginName);

        return [
            $tag . '-assets' => [
                $assetsPath => public_path("plugins/{$slugName}"),
            ],
        ];
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
