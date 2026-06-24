<?php

namespace Siaoynli\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Abstract Plugin
 *
 * 所有插件的基类，提供 PluginInterface 的默认实现。
 *
 * 设计原则：
 * - 延迟加载：构造函数不做 I/O，所有属性按需初始化
 * - 生命周期分离：register() 仅绑定容器，boot() 处理路由/事件
 * - 路由缓存兼容：尊重 app()->routesAreCached()
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected bool $enabled = true;
    protected array $config = [];

    /**
     * 以下属性均为延迟初始化，首次访问时从文件系统加载
     */
    protected ?string $basePath = null;
    protected ?array $composer = null;
    protected ?string $name = null;
    protected ?string $version = null;
    protected ?string $namespace = null;
    protected bool $configLoaded = false;

    /**
     * 构造函数不做任何 I/O 操作
     *
     * 所有文件系统操作延迟到首次调用相关方法时执行。
     * 这样即使插件被禁用（isEnabled=false），也不会浪费 I/O。
     */
    public function __construct()
    {
        // 空构造函数 — 所有初始化延迟执行
    }

    // ================================================================
    // PluginInterface 实现
    // ================================================================

    /**
     * 获取插件名称（Composer 包名）
     */
    public function getName(): string
    {
        return $this->name ??= $this->loadComposerConfig()['name'] ?? 'unknown';
    }

    /**
     * 获取插件版本号
     */
    public function getVersion(): string
    {
        return $this->version ??= $this->loadComposerConfig()['version'] ?? '0.0.0';
    }

    /**
     * 获取插件描述
     */
    public function getDescription(): string
    {
        return $this->loadComposerConfig()['description'] ?? '';
    }

    /**
     * 判断插件是否启用
     */
    public function isEnabled(): bool
    {
        $this->ensureConfigLoaded();
        return $this->enabled;
    }

    /**
     * 注册阶段 — 配置合并 + 服务提供者注册
     *
     * 仅做容器绑定，不使用其他服务。
     */
    public function register(): void
    {
        $this->mergeConfig();
        $this->registerServiceProviders();
    }

    /**
     * 启动阶段 — 子类可覆写此方法注册事件监听、中间件等
     *
     * 默认实现为空，子类按需覆写。
     */
    public function boot(): void
    {
        // 子类可覆写
    }

    /**
     * 注册路由（兼容路由缓存）
     */
    public function registerRoutes(): void
    {
        // 路由已缓存时跳过加载
        if (app()->routesAreCached()) {
            return;
        }

        $routesDir = $this->getBasePath() . '/routes';

        if (!is_dir($routesDir)) {
            return;
        }

        $routeFiles = File::glob($routesDir . '/*.php') ?: [];

        if (empty($routeFiles)) {
            return;
        }

        $prefix = $this->getRoutePrefix();
        $middleware = $this->getMiddleware();

        Route::group(
            [
                'prefix' => $prefix,
                'middleware' => $middleware,
            ],
            function () use ($routeFiles) {
                foreach ($routeFiles as $routesFile) {
                    require $routesFile;
                }
            }
        );
    }

    // ================================================================
    // 公共访问方法（非接口要求，供外部和 Publisher 使用）
    // ================================================================

    /**
     * 获取插件根目录
     */
    public function getBasePath(): string
    {
        return $this->basePath ??= $this->resolvePath();
    }

    /**
     * 获取插件配置
     */
    public function getConfig(?string $key = null)
    {
        $this->ensureConfigLoaded();

        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    /**
     * 获取路由前缀
     */
    public function getRoutePrefix(): string
    {
        $this->ensureConfigLoaded();
        return $this->config['route_prefix'] ?? strtolower(str_replace('/', '-', $this->getName()));
    }

    /**
     * 获取中间件
     */
    public function getMiddleware(): array
    {
        $this->ensureConfigLoaded();
        return $this->config['middleware'] ?? ['api'];
    }

    /**
     * 获取插件 PSR-4 命名空间
     */
    public function getPluginNamespace(): string
    {
        if ($this->namespace !== null) {
            return $this->namespace;
        }

        $psr4 = $this->loadComposerConfig()['autoload']['psr-4'] ?? [];
        $firstNamespace = array_key_first($psr4);

        return $this->namespace = $firstNamespace
            ? rtrim($firstNamespace, '\\')
            : 'Plugins';
    }

    // ================================================================
    // 内部方法（延迟加载实现）
    // ================================================================

    /**
     * 解析插件根目录（向上查找 composer.json）
     */
    protected function resolvePath(): string
    {
        $reflection = new \ReflectionClass($this);
        $dir = dirname($reflection->getFileName());

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return dirname($reflection->getFileName());
    }

    /**
     * 加载并缓存 composer.json（惰性）
     */
    protected function loadComposerConfig(): array
    {
        if ($this->composer !== null) {
            return $this->composer;
        }

        $composerFile = $this->getBasePath() . '/composer.json';

        if (file_exists($composerFile)) {
            $this->composer = json_decode(file_get_contents($composerFile), true) ?: [];
        } else {
            $this->composer = [];
        }

        return $this->composer;
    }

    /**
     * 确保配置已加载
     */
    protected function ensureConfigLoaded(): void
    {
        if ($this->configLoaded) {
            return;
        }

        $this->loadConfig();
        $this->configLoaded = true;
    }

    /**
     * 加载配置（优先使用应用配置，回退到插件自带配置）
     */
    protected function loadConfig(): void
    {
        // 优先使用应用级别的配置（config/plugins/vendor-package.php）
        $slugName = str_replace('/', '-', $this->getName());
        $appConfig = config("plugins.{$slugName}", []);

        if (!empty($appConfig)) {
            $this->config = $appConfig;
            $this->enabled = $this->config['enabled'] ?? true;
            return;
        }

        // 回退到插件自带配置
        $configFile = $this->getBasePath() . '/config/plugin.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
            $this->enabled = $this->config['enabled'] ?? true;
        }
    }

    /**
     * 合并插件配置到应用配置
     *
     * 使用 Laravel 原生逻辑：应用配置优先，插件配置填充缺省值。
     */
    protected function mergeConfig(): void
    {
        $configFile = $this->getBasePath() . '/config/plugin.php';

        if (!file_exists($configFile)) {
            return;
        }

        $slugName = str_replace('/', '-', $this->getName());
        $pluginDefaults = require $configFile;
        $appConfig = config("plugins.{$slugName}", []);

        // 应用配置优先（与 Laravel 的 mergeConfigFrom 一致）
        config(["plugins.{$slugName}" => array_merge($pluginDefaults, $appConfig)]);
    }

    /**
     * 自动发现并注册插件内的 ServiceProvider
     */
    protected function registerServiceProviders(): void
    {
        $providersDir = $this->getBasePath() . '/src/Providers';

        if (!is_dir($providersDir)) {
            return;
        }

        $files = File::glob($providersDir . '/*.php') ?: [];
        $namespace = $this->getPluginNamespace() . '\\Providers';

        foreach ($files as $filePath) {
            $className = basename($filePath, '.php');
            $fullClass = $namespace . '\\' . $className;

            if (class_exists($fullClass)) {
                app()->register($fullClass);
            }
        }
    }
}
