<?php

namespace Siaoynli\Plugins\Registry;

use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * Plugin Registry
 *
 * 管理已注册插件的增删查，作为插件实例的唯一存储。
 */
class PluginRegistry
{
    /**
     * @var array<string, PluginInterface>
     */
    protected array $plugins = [];

    /**
     * 注册一个插件实例
     */
    public function register(string $name, PluginInterface $plugin): void
    {
        $this->plugins[$name] = $plugin;
        \Log::debug("Plugin registered: {$name}");
    }

    /**
     * 获取指定插件
     */
    public function get(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * 检查插件是否已注册
     */
    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * 获取所有已注册插件
     *
     * @return array<string, PluginInterface>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * 获取已注册插件数量
     */
    public function count(): int
    {
        return count($this->plugins);
    }

    /**
     * 列出所有插件的摘要信息（供 CLI 展示）
     */
    public function list(): array
    {
        $list = [];

        foreach ($this->plugins as $name => $plugin) {
            $list[] = [
                'package_name' => $name,
                'display_name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'description' => $plugin->getDescription(),
                'enabled' => $plugin->isEnabled(),
            ];
        }

        return $list;
    }
}
