<?php

namespace Siaoynli\Plugins;

use Siaoynli\Plugins\Contracts\PluginInterface;
use Siaoynli\Plugins\Registry\PluginRegistry;

/**
 * Plugin Manager (Facade)
 *
 * 向后兼容的门面类，代理到 PluginRegistry。
 * 新代码应直接注入 PluginRegistry 使用。
 *
 * @deprecated 请直接使用 PluginRegistry
 */
class PluginManager
{
    protected PluginRegistry $registry;

    public function __construct(PluginRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * 获取所有已注册插件
     *
     * @return array<string, PluginInterface>
     */
    public function getPlugins(): array
    {
        return $this->registry->all();
    }

    /**
     * 获取指定插件
     */
    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->registry->get($name);
    }

    /**
     * 检查插件是否已注册
     */
    public function hasPlugin(string $name): bool
    {
        return $this->registry->has($name);
    }

    /**
     * 列出所有插件摘要
     */
    public function listPlugins(): array
    {
        return $this->registry->list();
    }
}
