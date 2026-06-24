<?php

namespace Siaoynli\Plugins\Contracts;

/**
 * Plugin Interface
 *
 * 所有插件必须实现的核心契约。
 * 遵循接口隔离原则，仅包含插件必须提供的核心能力。
 */
interface PluginInterface
{
    /**
     * 获取插件名称（Composer 包名，如 vendor/package）
     */
    public function getName(): string;

    /**
     * 获取插件版本号
     */
    public function getVersion(): string;

    /**
     * 获取插件描述
     */
    public function getDescription(): string;

    /**
     * 判断插件是否启用
     */
    public function isEnabled(): bool;

    /**
     * 注册阶段 — 仅做容器绑定、配置合并
     *
     * 此时所有 ServiceProvider 的 register() 已执行完毕，
     * 但不应使用其他服务（它们可能尚未 boot）。
     */
    public function register(): void;

    /**
     * 启动阶段 — 路由、事件监听、中间件等
     *
     * 此时所有 ServiceProvider 均已注册，可安全使用任何服务。
     */
    public function boot(): void;

    /**
     * 注册插件路由
     */
    public function registerRoutes(): void;
}
