<?php

namespace Siaoynli\Plugins\Contracts;

/**
 * Plugin Interface
 *
 * 所有插件都必须实现此接口
 */
interface PluginInterface
{
    /**
     * 获取插件名称
     */
    public function getName(): string;

    /**
     * 获取插件版本
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
     * 注册插件（注册服务、配置、事件监听等）
     */
    public function register(): void;

    /**
     * 注册路由
     */
    public function registerRoutes(): void;

    /**
     * 发布资源（迁移文件、配置文件等）
     */
    public function publishAssets(): void;

    /**
     * 获取插件的根目录
     */
    public function getBasePath(): string;

    /**
     * 获取配置
     */
    public function getConfig(?string $key = null);

    /**
     * 加载配置
     */
    public function loadConfig(): void;

    /**
     * 获取路由前缀
     */
    public function getRoutePrefix(): string;

    /**
     * 获取中间件
     */
    public function getMiddleware(): array;
}