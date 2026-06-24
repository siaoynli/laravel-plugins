<?php

namespace Siaoynli\Plugins;

use Illuminate\Support\Facades\File;

/**
 * Plugin Manifest
 *
 * 缓存插件发现结果到 bootstrap/cache/plugins.php。
 * 借鉴 Laravel 的 PackageManifest 和 nwidart/laravel-modules 的 ModuleManifest。
 *
 * 生产环境直接 require 缓存文件，实现零文件系统扫描。
 */
class PluginManifest
{
    protected string $manifestPath;

    /**
     * 内存中的 manifest 缓存
     */
    protected ?array $manifest = null;

    public function __construct(string $manifestPath)
    {
        $this->manifestPath = $manifestPath;
    }

    /**
     * 获取 manifest 数据（优先读缓存文件，其次内存缓存）
     */
    public function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if ($this->isCached()) {
            return $this->manifest = require $this->manifestPath;
        }

        return [];
    }

    /**
     * 检查是否存在有效的缓存文件
     */
    public function isCached(): bool
    {
        return file_exists($this->manifestPath);
    }

    /**
     * 将 manifest 数据写入缓存文件
     */
    public function write(array $manifest): void
    {
        $this->manifest = $manifest;

        $dir = dirname($this->manifestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL;
        file_put_contents($this->manifestPath, $content, LOCK_EX);
    }

    /**
     * 清除缓存文件
     */
    public function clear(): void
    {
        $this->manifest = null;

        if (file_exists($this->manifestPath)) {
            unlink($this->manifestPath);
        }
    }

    /**
     * 获取缓存文件路径
     */
    public function getManifestPath(): string
    {
        return $this->manifestPath;
    }

    /**
     * 设置 manifest 数据（不写入文件）
     */
    public function setManifest(array $manifest): void
    {
        $this->manifest = $manifest;
    }
}
