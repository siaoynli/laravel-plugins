<?php

namespace Siaoynli\Plugins\Discovery;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Siaoynli\Plugins\Contracts\PluginInterface;
use Siaoynli\Plugins\PluginManifest;

/**
 * Plugin Discovery
 *
 * 负责发现所有插件，三阶段扫描：
 * 1. 手动配置（config/plugin.php → plugins 数组）
 * 2. Vendor 自动发现（installed.json → extra.plugin.class）
 * 3. 本地包扫描（packages/ 目录）
 *
 * 支持 manifest 缓存，生产环境零文件扫描。
 */
class PluginDiscovery
{
    protected string $basePath;
    protected string $packagesPath;

    /**
     * 缓存 installed.json 解析结果（单次请求内复用）
     */
    protected ?array $installedPackages = null;

    public function __construct()
    {
        $this->basePath = base_path();
        $this->packagesPath = config('plugins.discovery.packages_path', $this->basePath . '/packages');
    }

    /**
     * 发现所有插件
     *
     * @return array<string, array{class: string, path: string}>  packageName => metadata
     */
    public function discover(PluginManifest $manifest): array
    {
        // 有缓存直接返回
        if ($manifest->isCached()) {
            \Log::debug('Loading plugins from manifest cache');
            return $manifest->getManifest();
        }

        \Log::debug('Building plugin manifest (no cache)');

        $plugins = [];

        // 阶段 1：手动配置（最高优先级）
        $plugins = array_merge($plugins, $this->fromConfig());

        // 阶段 2：Vendor 自动发现
        $plugins = array_merge($plugins, $this->fromVendor($plugins));

        // 阶段 3：本地包扫描
        if (config('plugins.discovery.scan_local_packages', true)) {
            $plugins = array_merge($plugins, $this->fromLocalPackages($plugins));
        }

        // 写入缓存
        if (config('plugins.cache.enabled', true)) {
            $manifest->write($plugins);
        }

        \Log::debug('Plugin manifest built', ['count' => count($plugins)]);

        return $plugins;
    }

    /**
     * 阶段 1：从 config/plugin.php 的 plugins 数组读取
     */
    protected function fromConfig(): array
    {
        $configPlugins = config('plugins.plugins', []);

        if (empty($configPlugins)) {
            return [];
        }

        $result = [];
        foreach ($configPlugins as $packageName => $pluginClass) {
            if (!is_string($pluginClass) || !class_exists($pluginClass)) {
                \Log::warning("Plugin class not found in config: {$pluginClass}");
                continue;
            }

            $result[$packageName] = [
                'class' => $pluginClass,
                'source' => 'config',
            ];
        }

        return $result;
    }

    /**
     * 阶段 2：从 vendor/composer/installed.json 自动发现
     */
    protected function fromVendor(array $existing): array
    {
        $packages = $this->getInstalledPackages();

        if (empty($packages)) {
            return [];
        }

        $result = [];
        foreach ($packages as $package) {
            $packageName = $package['name'] ?? null;
            if (!$packageName || isset($existing[$packageName]) || isset($result[$packageName])) {
                continue;
            }

            $pluginClass = Arr::get($package, 'extra.plugin.class');
            if (!$pluginClass) {
                continue;
            }

            $result[$packageName] = [
                'class' => $pluginClass,
                'source' => 'vendor',
            ];
        }

        return $result;
    }

    /**
     * 阶段 3：扫描 packages/ 目录中的本地包
     */
    protected function fromLocalPackages(array $existing): array
    {
        if (!is_dir($this->packagesPath)) {
            return [];
        }

        $result = [];

        try {
            // 使用 glob 替代 RecursiveDirectoryIterator，性能更好
            $composerFiles = File::glob($this->packagesPath . '/*/composer.json')
                ?: [];
            // 也扫描两层：packages/vendor/plugin/composer.json
            $composerFiles = array_merge(
                $composerFiles,
                File::glob($this->packagesPath . '/*/*/composer.json') ?: []
            );

            foreach ($composerFiles as $composerFile) {
                $composer = json_decode(File::get($composerFile), true);
                if (!$composer) {
                    continue;
                }

                $packageName = $composer['name'] ?? null;
                if (!$packageName || isset($existing[$packageName]) || isset($result[$packageName])) {
                    continue;
                }

                $packagePath = dirname($composerFile);
                $pluginClass = $this->resolvePluginClass($packageName, $packagePath, $composer);

                if ($pluginClass) {
                    $result[$packageName] = [
                        'class' => $pluginClass,
                        'path' => $packagePath,
                        'source' => 'local',
                    ];
                }
            }
        } catch (Exception $e) {
            \Log::error('Error scanning local packages: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 解析本地包的插件类名
     */
    protected function resolvePluginClass(string $packageName, string $packagePath, array $composer): ?string
    {
        // 方式 1：composer.json 中显式声明
        $pluginClass = Arr::get($composer, 'extra.plugin.class');
        if ($pluginClass && class_exists($pluginClass)) {
            return $pluginClass;
        }

        // 方式 2：根据 PSR-4 自动推断
        $psr4 = Arr::get($composer, 'autoload.psr-4', []);

        foreach ($psr4 as $namespace => $paths) {
            $namespace = rtrim($namespace, '\\');
            $suffix = str_replace('-', '', ucwords(basename($packageName), '-'));
            $guessedClass = $namespace . '\\' . $suffix . 'Plugin';

            if (class_exists($guessedClass) && is_subclass_of($guessedClass, PluginInterface::class)) {
                return $guessedClass;
            }
        }

        return null;
    }

    /**
     * 加载并缓存 installed.json
     */
    protected function getInstalledPackages(): array
    {
        if ($this->installedPackages !== null) {
            return $this->installedPackages;
        }

        $installedFile = $this->basePath . '/vendor/composer/installed.json';

        if (!File::exists($installedFile)) {
            return $this->installedPackages = [];
        }

        try {
            $installed = json_decode(File::get($installedFile), true);
            return $this->installedPackages = $installed['packages'] ?? $installed;
        } catch (Exception $e) {
            \Log::error('Failed to load installed.json: ' . $e->getMessage());
            return $this->installedPackages = [];
        }
    }
}
