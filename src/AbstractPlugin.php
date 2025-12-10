<?php

namespace Siaoynli\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Abstract Plugin Class
 *
 * 所有插件的基类，提供了插件的基本功能实现
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected bool $enabled = true;
    protected array $config = [];
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = $this->resolvePath();
        $this->loadConfig();
    }

    /**
     * 解析插件的基础路径
     */
    protected function resolvePath(): string
    {
        $reflection = new \ReflectionClass($this);
        $pluginDir = dirname($reflection->getFileName());

        // 向上遍历找到插件的根目录
        while ($pluginDir !== '/') {
            if (File::exists($pluginDir . '/composer.json')) {
                return $pluginDir;
            }
            $pluginDir = dirname($pluginDir);
        }

        return dirname($reflection->getFileName());
    }

    /**
     * 加载配置文件
     */
    public function loadConfig(): void
    {
        $configFile = $this->basePath . '/config/plugin.php';

        if (File::exists($configFile)) {
            $this->config = require $configFile;
            $this->enabled = $this->config['enabled'] ?? true;
        }
    }

    /**
     * 默认注册方法
     */
    public function register(): void
    {
        // 注册配置
        $configFile = $this->basePath . '/config/plugin.php';
        if (File::exists($configFile)) {
            $this->mergeConfigFrom($configFile, strtolower($this->getPluginName()));
        }

        // 注册服务提供者
        $this->registerServiceProviders();
    }

    /**
     * 注册服务提供者
     */
    protected function registerServiceProviders(): void
    {
        $providersFile = $this->basePath . '/src/Providers';

        if (!File::isDirectory($providersFile)) {
            return;
        }

        $files = File::files($providersFile);
        $namespace = $this->getPluginNamespace() . '\\Providers';

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && $file->getBasename() !== '.gitkeep') {
                $class = $namespace . '\\' . $file->getBasename('.php');
                if (class_exists($class)) {
                    app()->register($class);
                }
            }
        }
    }

    /**
     * 默认路由注册方法
     */
    public function registerRoutes(): void
    {
        $routesDir = $this->basePath . '/routes';

        if (!is_dir($routesDir)) {
            return;
        }

        // 循环遍历目录下的所有文件
        $files = File::files($routesDir);
        Route::group(
            [
                'prefix' => $this->getRoutePrefix(),
                'middleware' => $this->getMiddleware(),
            ],
            function () use ($files, $routesDir) {
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php' && $file->getBasename() !== '.gitkeep') {
                        $routesFile = $routesDir . '/' . $file->getBasename();
                        if (File::exists($routesFile)) {
                            require $routesFile;
                        }
                    }
                }
            }
        );
    }

    /**
     * 发布资源
     */
    public function publishAssets(): void
    {
        // 发布迁移文件
        $migrationsPath = $this->basePath . '/database/migrations';
        if (File::isDirectory($migrationsPath)) {
            $this->publishesMigrations([
                $migrationsPath => database_path('migrations'),
            ]);
        }

        // 发布配置文件
        $configFile = $this->basePath . '/config/plugin.php';
        if (File::exists($configFile)) {
            $this->publishesConfig([
                $configFile => config_path('plugins/' . $this->getPluginName() . '.php'),
            ]);
        }

        // 发布视图
        $viewsPath = $this->basePath . '/resources/views';
        if (File::isDirectory($viewsPath)) {
            $this->publishesViews([
                $viewsPath => resource_path('views/plugins/' . $this->getPluginName()),
            ]);
        }

        // 发布资源文件
        $assetsPath = $this->basePath . '/resources/assets';
        if (File::isDirectory($assetsPath)) {
            $this->publishesAssets([
                $assetsPath => public_path('plugins/' . $this->getPluginName()),
            ]);
        }
    }

    /**
     * 合并配置（辅助方法）
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        config([$key => array_merge(config($key, []), require $path)]);
    }

    /**
     * 发布迁移（辅助方法）
     */
    protected function publishesMigrations(array $paths): void
    {
        foreach ($paths as $from => $to) {
            if (File::isDirectory($from)) {
                File::copyDirectory($from, $to);
            }
        }
    }

    /**
     * 发布配置（辅助方法）
     */
    protected function publishesConfig(array $paths): void
    {
        foreach ($paths as $from => $to) {
            if (File::exists($from)) {
                File::copy($from, $to);
            }
        }
    }

    /**
     * 发布视图（辅助方法）
     */
    protected function publishesViews(array $paths): void
    {
        foreach ($paths as $from => $to) {
            if (File::isDirectory($from)) {
                File::copyDirectory($from, $to);
            }
        }
    }

    /**
     * 发布资源（辅助方法）
     */
    protected function publishesAssets(array $paths): void
    {
        foreach ($paths as $from => $to) {
            if (File::isDirectory($from)) {
                File::copyDirectory($from, $to);
            }
        }
    }

    /**
     * 获取插件名称
     */
    public function getPluginName(): string
    {
        $composerFile = $this->basePath . '/composer.json';
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            return $composer['name'] ?? 'unknown';
        }
        return 'unknown';
    }

    /**
     * 获取插件命名空间
     */
    public function getPluginNamespace(): string
    {
        $composerFile = $this->basePath . '/composer.json';
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            $psr4 = $composer['autoload']['psr-4'] ?? [];
            return key($psr4) ? rtrim(key($psr4), '\\') : 'Plugins';
        }
        return 'Plugins';
    }

    /**
     * 获取路由前缀
     */
    public function getRoutePrefix(): string
    {
        return $this->config['route_prefix'] ?? strtolower($this->getPluginName());
    }

    /**
     * 获取中间件
     */
    public function getMiddleware(): array
    {
        return $this->config['middleware'] ?? ['api'];
    }

    /**
     * 获取基础路径
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * 获取配置
     */
    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }

    /**
     * 判断是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}