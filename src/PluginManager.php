<?php

namespace Siaoynli\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Plugin Manager
 *
 * 管理所有插件的加载、注册和启动
 * 支持三种来源：配置文件、Vendor 包、本地包
 */
class PluginManager
{
    protected array $plugins = [];
    protected string $basePath;
    protected string $packagesPath;

    public function __construct()
    {
        $this->basePath = base_path();
        $this->packagesPath = $this->basePath . '/packages';
    }

    /**
     * 加载所有插件
     * 支持三种来源：
     * 1. config/plugins.php 配置文件
     * 2. vendor 中已安装的包（vendor/composer/installed.json）
     * 3. packages 目录中的本地自定义包
     */
    public function loadPlugins(): void
    {
        \Log::info('========== Plugin Loading Started ==========');

        // 方式 1：从配置文件读取（优先级最高）
        $this->loadFromConfig();

        // 方式 2：从 vendor 中自动扫描（优先级中）
        $this->autoDiscoverPlugins();

        // 方式 3：扫描 packages 目录中的本地包（优先级低）
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
                $this->registerPlugin($packageName, $pluginClass);
            }
        } catch (\Exception $e) {
            \Log::error('Error loading plugins from config: ' . $e->getMessage());
        }
    }

    /**
     * 自动扫描 vendor/composer/installed.json
     */
    protected function autoDiscoverPlugins(): void
    {
        $installedFile = $this->basePath . '/vendor/composer/installed.json';

        if (!File::exists($installedFile)) {
            \Log::debug('installed.json not found');
            return;
        }

        try {
            $installed = json_decode(File::get($installedFile), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Invalid installed.json');
                return;
            }
            // Composer 2.0+ 的结构
            $packages = $installed['packages'] ?? $installed;

            \Log::info('Auto-discovering plugins from installed.json');

            foreach ($packages as $package) {
                $this->checkAndRegisterPackageAsPlugin($package);
            }
        } catch (\Exception $e) {
            \Log::error('Error scanning installed.json: ' . $e->getMessage());
        }
    }

    /**
     * 扫描项目根目录下的 packages 目录
     * 发现本地开发的包
     */
    protected function discoverLocalPackages(): void
    {
        if (!is_dir($this->packagesPath)) {
            \Log::debug('packages directory not found');
            return;
        }

        \Log::info('Scanning local packages directory: ' . $this->packagesPath);

        try {
            $packages = $this->scanDirectoryForPackages($this->packagesPath, 1);
            foreach ($packages as $packageDir) {
                $packagePath = $this->packagesPath . '/' . $packageDir;
                // 只处理目录
                if (!is_dir($packagePath)) {
                    continue;
                }
                $this->discoverPluginInLocalPackage($packageDir, $packagePath);
            }
        } catch (\Exception $e) {
            \Log::error('Error scanning local packages: ' . $e->getMessage());
        }
    }

    /**
     * 内部递归函数：扫描指定路径以查找 packages。
     *
     * @param string $path 当前要扫描的绝对路径。
     * @param int $currentDepth 当前的扫描深度（1 或 2）。
     * @param string $relativePathPrefix 当前包的相对路径前缀。
     * @return array<string> 找到的相对包路径数组。
     */
    protected function scanDirectoryForPackages(
        string $path,
        int $currentDepth,
        string $relativePathPrefix = ''
    ): array {
        // 限制最大深度为 2
        if ($currentDepth > 2) {
            return [];
        }

        $packages = [];

        // 使用 File::directories() 获取当前路径下的所有目录
        $directories = File::directories($path);

        foreach ($directories as $directoryPath) {
            $dirName = basename($directoryPath);

            // 构建完整的相对路径 (例如：vendor-A 或 vendor-A/package-B)
            $currentRelativePath = $relativePathPrefix
                ? $relativePathPrefix . DIRECTORY_SEPARATOR . $dirName
                : $dirName;

            $composerPath = $directoryPath . DIRECTORY_SEPARATOR . 'composer.json';

            // 1. 检查当前目录是否是包 (Level 1 或 Level 2)
            if (File::exists($composerPath)) {
                $packages[] = $currentRelativePath;
            } elseif ($currentDepth < 2) {
                // 2. 如果当前目录不是包，并且当前深度小于 2，则继续递归扫描下一级

                $subPackages = $this->scanDirectoryForPackages(
                    $directoryPath,
                    $currentDepth + 1,
                    $currentRelativePath
                );

                $packages = array_merge($packages, $subPackages);
            }
        }

        return $packages;
    }

    /**
     * 发现本地包中的插件
     * 支持两种方式：
     * 1. composer.json 中有 extra.plugin.class 配置
     * 2. 在包的根目录或 src 目录中查找实现 PluginInterface 的类
     */
    protected function discoverPluginInLocalPackage(string $packageName, string $packagePath): void
    {
        \Log::info("Checking local package: {$packageName}");

        // 方式 1：检查 composer.json 中的 plugin 配置
        $composerFile = $packagePath . '/composer.json';
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            $pluginClass = $composer['extra']['plugin']['class'] ?? null;
            if ($pluginClass) {
                \Log::info("Found plugin in local package {$packageName}: {$pluginClass}");

                // 构建完整的包名称（如果 composer.json 中有 name，使用它）
                $fullPackageName = $composer['name'] ?? $packageName;
                $this->registerPlugin($fullPackageName, $pluginClass);
                return;
            }
        }

        // 方式 2：自动扫描 src 目录查找 Plugin 类
        $srcPath = $packagePath . '/src';
        if (is_dir($srcPath)) {
            $this->scanDirectoryForPlugins($packageName, $srcPath);
        }

        // 方式 3：扫描根目录
        $this->scanDirectoryForPlugins($packageName, $packagePath);
    }

    /**
     * 扫描目录中查找 Plugin 类
     * 寻找继承 AbstractPlugin 或实现 PluginInterface 的类
     */
    protected function scanDirectoryForPlugins(string $packageName, string $directory): void
    {
        try {
            // 递归扫描 PHP 文件
            $files = File::allFiles($directory);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                // 跳过某些目录
                if (
                    strpos($file->getPathname(), '/tests/') !== false ||
                    strpos($file->getPathname(), '/Test') !== false
                ) {
                    continue;
                }

                $this->checkFileForPlugin($packageName, $file->getPathname());
            }
        } catch (\Exception $e) {
            \Log::debug("Error scanning directory {$directory}: " . $e->getMessage());
        }
    }

    /**
     * 检查单个 PHP 文件是否包含插件类
     */
    protected function checkFileForPlugin(string $packageName, string $filePath): void
    {
        try {
            $content = File::get($filePath);
            // 提取命名空间
            if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $matches)) {
                $namespace = $matches[1];

                // 提取类名
                if (preg_match('/class\s+(\w+)\s+/', $content, $matches)) {
                    $className = $matches[1];
                    $fullClassName = $namespace . '\\' . $className;

                    // 检查是否实现了 PluginInterface
                    if (
                        class_exists($fullClassName) &&
                        is_subclass_of($fullClassName, PluginInterface::class)
                    ) {
                        \Log::info("Found plugin class in local package {$packageName}: {$fullClassName}");
                        $this->registerPlugin($packageName, $fullClassName);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Error checking file {$filePath}: " . $e->getMessage());
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
        // 检查 extra.plugin.class 字段
        $pluginClass = $package['extra']['plugin']['class'] ?? null;

        if (!$pluginClass) {
            return;
        }
        // 避免重复注册（来自 config 的优先）
        if (isset($this->plugins[$packageName])) {
            \Log::debug("Plugin {$packageName} already registered");
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
            // 检查类是否存在
            if (!class_exists($pluginClass)) {
                \Log::warning("Plugin class not found: {$pluginClass}", [
                    'package' => $packageName
                ]);
                return;
            }

            // 实例化插件
            $plugin = new $pluginClass();

            // 验证是否实现了接口
            if (!($plugin instanceof PluginInterface)) {
                \Log::warning("Plugin does not implement PluginInterface", [
                    'package' => $packageName,
                    'class' => $pluginClass
                ]);
                return;
            }

            // 检查插件是否启用
            if (!$plugin->isEnabled()) {
                \Log::info("Plugin is disabled: {$packageName}");
                return;
            }

            $this->plugins[$packageName] = $plugin;

            \Log::info("✓ Plugin registered", [
                'package' => $packageName,
                'class' => $pluginClass,
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion()
            ]);
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
                'display_name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'description' => $plugin->getDescription(),
                'enabled' => $plugin->isEnabled(),
                'route_prefix' => $plugin->getRoutePrefix(),
            ];
        }
        return $list;
    }

    /**
     * 发布所有插件的资源
     * 包括迁移、配置、视图、资源文件等
     */
    public function publishAssets(): void
    {
        \Log::info('========== Publishing Plugin Assets ==========');

        foreach ($this->plugins as $name => $plugin) {
            try {
                $this->publishPluginAssets($name, $plugin);
            } catch (\Exception $e) {
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

        // 发布迁移文件
        $this->publishMigrations($name, $basePath);

        // 发布配置文件
        $this->publishConfig($name, $basePath);

        // 发布视图文件
        $this->publishViews($name, $basePath);

        // 发布资源文件
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
            $files = File::allFiles($migrationsPath);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $filename = $file->getBasename();
                    $source = $file->getRealPath();
                    $destination = database_path('migrations/' . $filename);

                    // 如果迁移文件已存在，跳过
                    if (!File::exists($destination)) {
                        File::copy($source, $destination);
                        \Log::info("Published migration: {$filename}");
                    }
                }
            }
        } catch (\Exception $e) {
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

            // 创建配置目录（如果不存在）
            $configDir = config_path('plugins');
            if (!is_dir($configDir)) {
                File::makeDirectory($configDir, 0755, true);
            }

            // 如果配置文件已存在，跳过
            if (!File::exists($destination)) {
                File::copy($configFile, $destination);
                \Log::info("Published config: {$pluginConfigName}.php");
            }
        } catch (\Exception $e) {
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

            // 创建目标目录（如果不存在）
            if (!is_dir($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            // 复制所有视图文件
            File::copyDirectory($viewsPath, $destination);
            \Log::info("Published views to: plugins/{$pluginName}");
        } catch (\Exception $e) {
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

            // 创建目标目录（如果不存在）
            if (!is_dir($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            // 复制所有资源文件
            File::copyDirectory($assetsPath, $destination);
            \Log::info("Published resources to: /plugins/{$pluginName}");
        } catch (\Exception $e) {
            \Log::warning("Error publishing resources for {$name}: " . $e->getMessage());
        }
    }

    /**
     * 发布特定插件的资源
     * 可以从命令行调用：php artisan plugin:publish vendor/plugin-name
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
        } catch (\Exception $e) {
            \Log::error("Error publishing plugin {$pluginName}: " . $e->getMessage());
            return false;
        }
    }
}