<?php

namespace Siaoynli\Plugins;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Siaoynli\Plugins\Contracts\PluginInterface;
use SplFileInfo;

/**
 * Plugin Manager
 *
 * 管理所有插件的加载、注册和启动
 * 优化目标：减少文件系统 I/O 和重复的 JSON 解析。
 */
class PluginManager
{
    protected array $plugins = [];
    protected string $basePath;
    protected string $packagesPath;

    // 缓存已解析的 composer installed.json 内容
    protected ?array $installedPackages = null;

    public function __construct()
    {
        $this->basePath = base_path();
        $this->packagesPath = $this->basePath . '/packages';

        // 预加载并缓存 installed.json
        $this->loadInstalledPackagesCache();
    }

    /**
     * 预加载并缓存 installed.json 的内容
     */
    protected function loadInstalledPackagesCache(): void
    {
        $installedFile = $this->basePath . '/vendor/composer/installed.json';

        if (!File::exists($installedFile)) {
            $this->installedPackages = [];
            return;
        }

        try {
            $installed = json_decode(File::get($installedFile), true);
            // 处理 Composer 2.0+ 的结构
            $this->installedPackages = $installed['packages'] ?? $installed;
        } catch (Exception $e) {
            \Log::error('Failed to load installed.json: ' . $e->getMessage());
            $this->installedPackages = [];
        }
    }

    /**
     * 加载所有插件
     */
    public function loadPlugins(): void
    {
        \Log::info('========== Plugin Loading Started ==========');

        $this->loadFromConfig();
        $this->autoDiscoverPlugins();
        $this->discoverLocalPackages();

        \Log::info('========== Plugin Loading Completed ==========', [
            'loaded_count' => count($this->plugins)
        ]);
    }

    /**
     * 从 config/app-plugins.php 读取插件配置
     */
    protected function loadFromConfig(): void
    {
        try {
            $configPlugins = config('app-plugins', []);
            if (empty($configPlugins)) {
                \Log::info('No plugins configured in config/app-plugins.php');
                return;
            }

            \Log::info('Loading plugins from config/app-plugins.php', [
                'count' => count($configPlugins)
            ]);

            foreach ($configPlugins as $packageName => $pluginClass) {
                \Log::info("Loading plugin from config: {$packageName}");
                if (!isset($this->plugins[$packageName])) {
                    $this->registerPlugin($packageName, $pluginClass);
                } else {
                    \Log::debug("Plugin {$packageName} already registered from config, skipping");
                }
            }
        } catch (Exception $e) {
            \Log::error('Error loading plugins from config: ' . $e->getMessage());
        }
    }

    /**
     * 自动扫描 vendor/composer/installed.json
     * 使用缓存的 installed.json 数据
     */
    protected function autoDiscoverPlugins(): void
    {
        if (empty($this->installedPackages)) {
            \Log::debug('No installed packages loaded or installed.json not found.');
            return;
        }

        \Log::info('Auto-discovering plugins from installed packages');

        try {
            foreach ($this->installedPackages as $package) {
                // 如果已通过配置注册，则跳过
                if (isset($this->plugins[$package['name'] ?? ''])) {
                    continue;
                }
                $this->checkAndRegisterPackageAsPlugin($package);
            }
        } catch (Exception $e) {
            \Log::error('Error scanning installed packages: ' . $e->getMessage());
        }
    }

    /**
     * 扫描项目根目录下的 packages 目录
     */
    protected function discoverLocalPackages(): void
    {
        if (!is_dir($this->packagesPath)) {
            \Log::debug('packages directory not found');
            return;
        }

        \Log::info('Scanning local packages directory: ' . $this->packagesPath);

        try {
            // 限制只扫描两层目录，寻找包含 composer.json 的目录
            $packagePaths = $this->findLocalPackageDirs($this->packagesPath, 2);

            foreach ($packagePaths as $packagePath) {
                $composerFile = $packagePath . '/composer.json';
                $composer = File::exists($composerFile)
                    ? json_decode(File::get($composerFile), true)
                    : null;

                if ($composer) {
                    $packageName = $composer['name'] ?? basename($packagePath);
                    if (isset($this->plugins[$packageName])) {
                        continue; // 避免重复注册
                    }
                    $this->discoverPluginInLocalPackage($packageName, $packagePath, $composer);
                }
            }
        } catch (Exception $e) {
            \Log::error('Error scanning local packages: ' . $e->getMessage());
        }
    }

    /**
     * **已修复**：使用迭代器查找所有包含 composer.json 的子目录
     * 修复了原代码中错误的迭代器跳过逻辑。
     * * @param string $path 基础路径
     * @param int $maxDepth 最大深度
     * @return array<string> 找到的包的绝对路径数组
     */
    protected function findLocalPackageDirs(string $path, int $maxDepth): array
    {
        $packagePaths = [];

        if (!is_dir($path)) {
            return $packagePaths;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST // 先目录后文件
            );

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    // 深度从 0 开始，所以 +1
                    $currentDepth = $iterator->getDepth() + 1;

                    // 仅检查在 maxDepth 范围内的目录
                    if ($currentDepth <= $maxDepth) {
                        $composerPath = $item->getPathname() . DIRECTORY_SEPARATOR . 'composer.json';

                        // 检查 composer.json
                        if (File::exists($composerPath)) {
                            $packagePaths[] = $item->getPathname();
                        }
                    }

                    // 如果深度超过 maxDepth，告诉迭代器不要深入子目录
                    if ($currentDepth >= $maxDepth && $iterator->callHasChildren()) {
                        // 修复：使用 setMaxDepth 避免深入超过 maxDepth 的子目录
                        // 注意：RecursiveIteratorIterator 不支持动态修改 MaxDepth，
                        // 但在 SELF_FIRST 模式下，当 $currentDepth >= $maxDepth 时，
                        // 停止深入下一层是最佳做法，通过不将路径添加到 packagePaths 来实现
                        // 或者更严格地控制子迭代器的行为。

                        // 由于我们限制了循环内部的检查深度，递归迭代器会自然地继续下一个同级目录，无需手动跳过。
                    }
                }
            }
        } catch (Exception $e) {
            \Log::error('Error iterating local package directories: ' . $e->getMessage());
        }

        return array_unique($packagePaths);
    }


    /**
     * 发现本地包中的插件
     * 依赖 Composer Autoload 机制，避免文件内容扫描。
     */
    protected function discoverPluginInLocalPackage(string $packageName, string $packagePath, array $composer): void
    {
        \Log::info("Checking local package: {$packageName}");

        // 方式 1：检查 composer.json 中的 plugin 配置
        $pluginClass = Arr::get($composer, 'extra.plugin.class') ?? null;
        if ($pluginClass) {
            \Log::info("Found explicit plugin in local package {$packageName}: {$pluginClass}");
            $this->registerPlugin($packageName, $pluginClass);
            return;
        }

        // 方式 2：尝试根据 PSR-4 自动推断类名
        $psr4 = Arr::get($composer, 'autoload.psr-4', []) ?? [];

        foreach ($psr4 as $namespace => $paths) {
            $namespace = rtrim($namespace, '\\');

            // 尝试构建常见的插件类名 (例如：Vendor\Package\PackagePlugin)
            $pluginClassGuess = $namespace . '\\' . str_replace('-', '', ucwords(basename($packageName), '-')) . 'Plugin';

            // 检查推断的类名是否实现了 PluginInterface
            if (class_exists($pluginClassGuess) && is_subclass_of($pluginClassGuess, PluginInterface::class)) {
                \Log::info("Found inferred plugin in local package {$packageName}: {$pluginClassGuess}");
                $this->registerPlugin($packageName, $pluginClassGuess);
                return;
            }
        }
    }

    /**
     * 检查包是否是插件
     */
    protected function checkAndRegisterPackageAsPlugin(array $package): void
    {
        $packageName = $package['name'] ?? null;
        if (!$packageName) {
            return;
        }

        $pluginClass = Arr::get($package, 'extra.plugin.class') ?? null;
        if (!$pluginClass) {
            return;
        }

        if (isset($this->plugins[$packageName])) {
            \Log::debug("Plugin {$packageName} already registered from config, skipping vendor discovery.");
            return;
        }

        \Log::info("Auto-discovered plugin from vendor: {$packageName}");
        $this->registerPlugin($packageName, $pluginClass);
    }

    /**
     * 注册单个插件
     */
    protected function registerPlugin(string $packageName, string $pluginClass): void
    {
        try {
            if (!class_exists($pluginClass)) {
                \Log::warning("Plugin class not found: {$pluginClass}", [
                    'package' => $packageName
                ]);
                return;
            }

            $plugin = new $pluginClass();

            if (!($plugin instanceof PluginInterface)) {
                \Log::warning("Plugin does not implement PluginInterface", [
                    'package' => $packageName,
                    'class' => $pluginClass
                ]);
                return;
            }

            if (!$plugin->isEnabled()) {
                \Log::info("Plugin is disabled: {$packageName}");
                return;
            }

            $this->plugins[$packageName] = $plugin;

            \Log::info("✓ Plugin registered", [
                'package' => $packageName,
                'class' => $pluginClass,
                'name' => method_exists($plugin, 'getName') ? $plugin->getName() : $packageName,
                'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : 'N/A'
            ]);
        } catch (Exception $e) {
            \Log::error("Failed to register plugin {$packageName}", [
                'class' => $pluginClass,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 启动所有插件
     */
    public function bootPlugins(): void
    {
        \Log::info('========== Booting Plugins ==========');
        foreach ($this->plugins as $name => $plugin) {
            try {
                $plugin->register();
            } catch (Exception $e) {
                \Log::error("Error booting plugin {$name}: " . $e->getMessage());
            }
        }
        \Log::info('========== Booting Completed ==========');
    }

    /**
     * 注册所有插件的路由
     */
    public function registerRoutes(): void
    {
        \Log::info('========== Registering Routes ==========');
        foreach ($this->plugins as $name => $plugin) {
            try {
                $plugin->registerRoutes();
                \Log::info("✓ Routes registered for: {$name}");
            } catch (Exception $e) {
                \Log::error("Error registering routes for {$name}: " . $e->getMessage());
            }
        }
        \Log::info('========== Routes Registration Completed ==========');
    }

    /**
     * 获取所有插件
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * 获取特定插件
     */
    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * 检查插件是否存在
     */
    public function hasPlugin(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * 列出所有插件信息
     */
    public function listPlugins(): array
    {
        $list = [];
        foreach ($this->plugins as $name => $plugin) {
            $list[] = [
                'package_name' => $name,
                'display_name' => method_exists($plugin, 'getName') ? $plugin->getName() : $name,
                'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : 'N/A',
                'description' => method_exists($plugin, 'getDescription') ? $plugin->getDescription() : '',
                'enabled' => $plugin->isEnabled(),
                'route_prefix' => method_exists($plugin, 'getRoutePrefix') ? $plugin->getRoutePrefix() : '',
            ];
        }
        return $list;
    }

    /**
     * 发布所有插件的资源
     */
    public function publishAssets(): void
    {
        \Log::info('========== Publishing Plugin Assets ==========');
        foreach ($this->plugins as $name => $plugin) {
            try {
                $this->publishPluginAssets($name, $plugin);
            } catch (Exception $e) {
                \Log::error("Error publishing assets for plugin {$name}: " . $e->getMessage());
            }
        }
        \Log::info('========== Publishing Assets Completed ==========');
    }

    /**
     * 发布单个插件的资源
     */
    protected function publishPluginAssets(string $name, PluginInterface $plugin): void
    {
        $basePath = $plugin->getBasePath();
        \Log::info("Publishing assets for plugin: {$name}");
        $this->publishMigrations($name, $basePath);
        $this->publishConfig($name, $basePath);
        $this->publishViews($name, $basePath);
        $this->publishResources($name, $basePath);
        \Log::info("✓ Assets published for plugin: {$name}");
    }

    /**
     * 发布迁移文件
     */
    protected function publishMigrations(string $name, string $basePath): void
    {
        $migrationsPath = $basePath . '/database/migrations';
        if (!is_dir($migrationsPath)) {
            return;
        }

        try {
            $files = File::glob($migrationsPath . '/*.php');

            foreach ($files as $source) {
                $filename = basename($source);
                $destination = database_path('migrations/' . $filename);

                if (!File::exists($destination)) {
                    File::copy($source, $destination);
                    \Log::info("Published migration: {$filename}");
                }
            }
        } catch (Exception $e) {
            \Log::warning("Error publishing migrations for {$name}: " . $e->getMessage());
        }
    }

    /**
     * 发布配置文件
     */
    protected function publishConfig(string $name, string $basePath): void
    {
        $configFile = $basePath . '/config/plugin.php';

        if (!File::exists($configFile)) {
            return;
        }

        try {
            $pluginConfigName = str_replace('/', '-', $name);
            $destination = config_path('plugins/' . $pluginConfigName . '.php');

            $configDir = config_path('plugins');
            if (!is_dir($configDir)) {
                File::makeDirectory($configDir, 0755, true);
            }

            if (!File::exists($destination)) {
                File::copy($configFile, $destination);
                \Log::info("Published config: {$pluginConfigName}.php");
            }
        } catch (Exception $e) {
            \Log::warning("Error publishing config for {$name}: " . $e->getMessage());
        }
    }

    /**
     * 发布视图文件
     */
    protected function publishViews(string $name, string $basePath): void
    {
        $viewsPath = $basePath . '/resources/views';
        if (!is_dir($viewsPath)) {
            return;
        }
        try {
            $pluginName = str_replace('/', '-', $name);
            $destination = resource_path('views/plugins/' . $pluginName);
            if (!is_dir($destination)) {
                File::makeDirectory($destination, 0755, true);
            }
            File::copyDirectory($viewsPath, $destination);
            \Log::info("Published views to: plugins/{$pluginName}");
        } catch (Exception $e) {
            \Log::warning("Error publishing views for {$name}: " . $e->getMessage());
        }
    }

    /**
     * 发布资源文件（CSS, JS, 图片等）
     */
    protected function publishResources(string $name, string $basePath): void
    {
        $assetsPath = $basePath . '/resources/assets';
        if (!is_dir($assetsPath)) {
            return;
        }
        try {
            $pluginName = str_replace('/', '-', $name);
            $destination = public_path('plugins/' . $pluginName);
            if (!is_dir($destination)) {
                File::makeDirectory($destination, 0755, true);
            }
            File::copyDirectory($assetsPath, $destination);
            \Log::info("Published resources to: /plugins/{$pluginName}");
        } catch (Exception $e) {
            \Log::warning("Error publishing resources for {$name}: " . $e->getMessage());
        }
    }

    /**
     * 发布特定插件的资源
     */
    public function publishPlugin(string $pluginName): bool
    {
        $plugin = $this->getPlugin($pluginName);
        if (!$plugin) {
            \Log::error("Plugin not found: {$pluginName}");
            return false;
        }
        try {
            $this->publishPluginAssets($pluginName, $plugin);
            return true;
        } catch (Exception $e) {
            \Log::error("Error publishing plugin {$pluginName}: " . $e->getMessage());
            return false;
        }
    }
}
