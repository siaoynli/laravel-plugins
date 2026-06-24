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
# 无测试目录，暂无测试命令
```

## 架构

```
PluginServiceProvider (Laravel 入口)
  ├── register(): 绑定 PluginManager 单例
  └── boot(): loadPlugins() → bootPlugins() → registerRoutes() → publishAssets()

PluginManager (核心调度器，单例)
  ├── loadFromConfig()        — 阶段1：读取 config/app-plugins.php（最高优先级）
  ├── autoDiscoverPlugins()   — 阶段2：扫描 vendor/composer/installed.json
  └── discoverLocalPackages() — 阶段3：递归扫描 packages/ 目录

AbstractPlugin (插件基类，实现 PluginInterface)
  ├── resolvePath()           — 查找插件根目录（向上查找 composer.json）
  ├── register()              — 合并配置 + 自动发现 Providers
  ├── registerRoutes()        — 加载 routes/*.php 并包裹 Route::group
  └── publishAssets()         — 直接拷贝迁移/配置/视图/资源文件

PluginInterface (契约，16 个方法)
```

## 目录结构

```
src/
├── Contracts/PluginInterface.php     # 插件契约
├── Providers/PluginServiceProvider.php # Laravel 服务提供者
├── Console/Commands/
│   ├── PluginListCommand.php         # plugin:list
│   └── PluginPublishCommand.php      # plugin:publish {plugin?}
├── AbstractPlugin.php                # 插件抽象基类
└── PluginManager.php                 # 核心管理器
config/
└── plugin.php                        # 默认配置（发布为 app-plugins.php）
```

## 编码规范

- **提交格式**: Conventional Commits（`feat()`, `perf()`, `refactor()` 等），允许中文前缀
- **注释/文档**: 中文
- **日志**: 大量使用 `\Log::info/warning/error/debug()`，带分隔标记
- **错误处理**: try-catch + 日志记录，不轻易 re-throw
- **性能**: 关键数据已缓存（composer.json、插件名、命名空间、installed.json）

## 注意事项

- CLI 模式下 `boot()` 会自动执行 `publishAssets()`（每次 artisan 命令都会拷贝文件）
- `plugin:publish` 命令不支持 README 中提到的 `--force` / `-v` 参数
- 无测试目录（`tests/`），PHPUnit 配置了但未使用
- 插件间无依赖解析机制（路线图中计划实现）
