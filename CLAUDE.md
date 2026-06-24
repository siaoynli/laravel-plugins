# CLAUDE.md

## 项目概述

`siaoynli/laravel-plugins` — Laravel 插件系统包，提供插件自动发现、注册、路由加载和资源发布功能。

- **PHP**: >= 8.2
- **Laravel**: ^11.0
- **命名空间**: `Siaoynli\Plugins\` → `src/`

## 常用命令

```bash
composer install              # 安装依赖
composer dump-autoload        # 刷新自动加载
php artisan plugin:cache      # 构建 manifest 缓存
php artisan plugin:clear      # 清除 manifest 缓存
php artisan plugin:list       # 列出已加载插件
php artisan plugin:publish    # 查看可发布资源标签
```

## 架构

```
PluginServiceProvider (Laravel 入口)
  ├── register(): 绑定单例 (Manifest, Discovery, Registry, Publisher, Manager)
  └── boot(): discover → register → boot → routes → publishes → commands

PluginManifest (缓存层)
  └── bootstrap/cache/plugins.php — 生产环境零扫描

PluginDiscovery (发现)
  ├── fromConfig()        — config/plugins.php → plugins 数组
  ├── fromVendor()        — installed.json → extra.plugin.class
  └── fromLocalPackages() — 扫描 packages/ 目录

PluginRegistry (注册表)
  └── 管理已注册插件实例的增删查

PluginPublisher (发布器)
  └── 声明式 publishes() 注册，用户通过 vendor:publish 手动发布

AbstractPlugin (插件基类)
  ├── 延迟加载：构造函数零 I/O，属性按需初始化
  ├── register() — 配置合并 + 服务提供者注册
  ├── boot()     — 子类覆写（事件、中间件等）
  └── registerRoutes() — 尊重 routesAreCached()

PluginInterface (精简契约：7 个核心方法)

PluginManager (向后兼容门面，代理到 PluginRegistry)

Events: PluginRegistered / PluginBooted
```

## 目录结构

```
src/
├── Contracts/PluginInterface.php       # 精简契约（7 方法）
├── Discovery/PluginDiscovery.php       # 插件发现（三阶段扫描）
├── Registry/PluginRegistry.php         # 插件注册表
├── Publisher/PluginPublisher.php       # 声明式资源发布
├── Events/
│   ├── PluginRegistered.php            # 注册事件
│   └── PluginBooted.php               # 启动事件
├── Console/Commands/
│   ├── PluginListCommand.php           # plugin:list
│   ├── PluginPublishCommand.php        # plugin:publish
│   ├── PluginCacheCommand.php          # plugin:cache
│   └── PluginClearCommand.php          # plugin:clear
├── Providers/PluginServiceProvider.php # Laravel 服务提供者
├── AbstractPlugin.php                  # 插件抽象基类
├── PluginManifest.php                  # Manifest 缓存
└── PluginManager.php                   # 向后兼容门面
config/
└── plugin.php                          # 配置（发现、缓存、手动注册）
```

## 编码规范

- **提交格式**: Conventional Commits（`feat()`, `perf()`, `refactor()` 等），允许中文前缀
- **注释/文档**: 中文
- **日志**: `error`/`warning` 用于真实异常，`debug` 用于流程追踪
- **错误处理**: try-catch + 日志记录，不轻易 re-throw
- **性能**: Manifest 缓存 + 延迟加载，生产环境零文件扫描

## 关键设计决策

- **Manifest 缓存**: 插件发现结果写入 `bootstrap/cache/plugins.php`，生产环境直接 require
- **声明式发布**: 使用 Laravel 原生 `publishes()` 注册，不在 boot 时自动拷贝文件
- **生命周期分离**: `register()` 仅绑定容器，`boot()` 处理路由/事件
- **路由缓存兼容**: `registerRoutes()` 检查 `routesAreCached()` 后跳过
- **向后兼容**: `PluginManager` 保留为 Registry 的门面，`plugin-manager` alias 保留
