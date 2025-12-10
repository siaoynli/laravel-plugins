<?php

namespace Siaoynli\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Abstract Plugin Class
 *
 * 所有插件的基类，提供了插件的基本功能实现。
 * 优化目标：减少文件系统 I/O 和重复的 JSON 解析。
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected bool $enabled = true;
    protected array $config = [];
    protected string $basePath;

    // **优化点:** 缓存 composer.json 的内容
    protected ?array $composer = null;
    // **优化点:** 缓存插件名称
    protected ?string $pluginName = null;
    // **优化点:** 缓存插件命名空间
    protected ?string $pluginNamespace = null;

    public function __construct()
    {
        // 1. 路径解析只在构造函数中执行一次
        $this->basePath = $this->resolvePath();

        // 2. 预加载 composer.json 并缓存
        $this->loadComposerConfig();

        // 3. 配置加载
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
     * **优化:** 缓存 composer.json 的内容，避免多次调用 I/O。
     */
    protected function loadComposerConfig(): void
    {
        $composerFile = $this->basePath . '/composer.json';
        if (File::exists($composerFile)) {
            $this->composer = json_decode(File::get($composerFile), true);
        } else {
            $this->composer = [];
        }
    }


    /**
     * 加载配置文件 (精简逻辑：优先使用应用配置，否则使用插件自带配置)
     */
    public function loadConfig(): void
    {
        // 尝试加载主应用已缓存或覆盖的配置
        $mainConfigKey = 'plugins.' . str_replace('/', '-', $this->getPluginName());
        $appConfig = config($mainConfigKey, []);

        if (!empty($appConfig)) {
            $this->config = $appConfig;
            $this->enabled = $this->config['enabled'] ?? true;
            return;
        }

        // 如果主应用没有配置，则加载插件自身的配置
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
     * 注册服务提供者 (优化: 使用 glob 简化文件查找)
     */
    protected function registerServiceProviders(): void
    {
        $providersDir = $this->basePath . '/src/Providers';

        if (!File::isDirectory($providersDir)) {
            return;
        }

        // **优化点:** 使用 glob 匹配所有 php 文件，减少循环内的字符串处理和文件检查
        $files = File::glob($providersDir . '/*.php');
        $namespace = $this->getPluginNamespace() . '\\Providers';

        foreach ($files as $filePath) {
            $fileName = basename($filePath, '.php');
            $class = $namespace . '\\' . $fileName;

            if (class_exists($class)) {
                app()->register($class);
            }
        }
    }

    /**
     * 默认路由注册方法 (优化: 使用 glob 简化文件查找)
     */
    public function registerRoutes(): void
    {
        $routesDir = $this->basePath . '/routes';

        if (!is_dir($routesDir)) {
            return;
        }

        // **优化点:** 使用 glob 匹配所有 php 文件
        $routeFiles = File::glob($routesDir . '/*.php');

        // 确保路由前缀和中间件只计算一次
        $prefix = $this->getRoutePrefix();
        $middleware = $this->getMiddleware();

        Route::group(
            [
                'prefix' => $prefix,
                'middleware' => $middleware,
            ],
            function () use ($routeFiles) {
                foreach ($routeFiles as $routesFile) {
                    // 文件路径已确定存在，直接 require
                    require $routesFile;
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
     * 获取插件名称 (优化: 缓存结果)
     */
    public function getPluginName(): string
    {
        if ($this->pluginName !== null) {
            return $this->pluginName;
        }

        // 直接使用已缓存的 $this->composer 属性
        $name = $this->composer['name'] ?? 'unknown';
        $this->pluginName = $name; // 缓存

        return $name;
    }

    /**
     * 获取插件命名空间 (优化: 缓存结果)
     */
    public function getPluginNamespace(): string
    {
        if ($this->pluginNamespace !== null) {
            return $this->pluginNamespace;
        }

        // 直接使用已缓存的 $this->composer 属性
        $psr4 = $this->composer['autoload']['psr-4'] ?? [];
        $namespace = key($psr4) ? rtrim(key($psr4), '\\') : 'Plugins';
        $this->pluginNamespace = $namespace; // 缓存

        return $namespace;
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
