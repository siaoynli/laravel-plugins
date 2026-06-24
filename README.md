# Laravel Plugins

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel 11.0+](https://img.shields.io/badge/Laravel-11.0%2B-red.svg)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net)

一个轻量高性能的 Laravel 11 插件系统框架，支持插件自动发现、Manifest 缓存、生命周期管理和声明式资源发布。

## ✨ 主要特性

- 🔌 **插件自动发现** — 配置文件、Composer 包、本地包三级来源
- ⚡ **Manifest 缓存** — 发现结果缓存到 `bootstrap/cache/plugins.php`，生产环境零文件扫描
- 🔄 **标准生命周期** — `register()` → `boot()` → `registerRoutes()` 严格分离
- 📦 **声明式发布** — 基于 Laravel 原生 `publishes()` + `vendor:publish`，不再自动覆盖文件
- 🎯 **延迟加载** — 构造函数零 I/O，所有属性按需初始化
- 🔔 **生命周期事件** — `PluginRegistered` / `PluginBooted` 事件，支持插件间松耦合通信
- 🛣️ **路由缓存兼容** — 自动尊重 `route:cache`，不重复加载路由
- ✅ **启用/禁用控制** — 通过配置文件控制每个插件的启用状态
- 💻 **Artisan 命令** — `plugin:list` / `plugin:publish` / `plugin:cache` / `plugin:clear`

## 📋 目录

- [安装](#安装)
- [快速开始](#快速开始)
- [创建插件](#创建插件)
- [插件发现与缓存](#插件发现与缓存)
- [插件生命周期](#插件生命周期)
- [资源发布](#资源发布)
- [配置参考](#配置参考)
- [API 参考](#api-参考)
- [Artisan 命令](#artisan-命令)
- [常见问题](#常见问题)
- [许可证](#许可证)

## 📦 安装

### 前置要求

- PHP >= 8.2
- Laravel >= 11.0

### 安装步骤

```bash
composer require siaoynli/laravel-plugins
```

Laravel 11 的包自动发现会自动注册 `PluginServiceProvider`，无需手动配置。

### 发布配置文件（可选）

```bash
php artisan vendor:publish --tag=laravel-plugins-config
```

这会在 `config/` 目录下生成 `plugins.php` 配置文件。

### 构建缓存（推荐）

```bash
php artisan plugin:cache
```

生产环境构建缓存后，后续请求不再扫描文件系统。

## 🚀 快速开始

### 1. 查看已加载的插件

```bash
php artisan plugin:list
```

输出示例：

```
1 plugin(s) loaded:

┌──────────────────────────┬──────────────┬─────────┬─────────┐
│ Package                  │ Name         │ Version │ Enabled │
├──────────────────────────┼──────────────┼─────────┼─────────┤
│ siaoynli/phone-auth      │ 手机验证码登录 │ 1.0.8   │ ✓       │
└──────────────────────────┴──────────────┴─────────┴─────────┘
```

### 2. 发布插件资源

```bash
# 查看可用的发布标签
php artisan plugin:publish

# 使用 Laravel 原生命令发布
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-config
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-migrations
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-views
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-assets
```

## 📝 创建插件

### 步骤 1: 创建目录结构

```
packages/my-vendor/my-plugin/
├── composer.json
├── config/
│   └── plugin.php              # 插件配置（可选）
├── src/
│   ├── MyPluginPlugin.php       # 插件主类
│   └── Providers/               # 服务提供者（可选，自动发现）
├── routes/
│   └── api.php                  # 路由文件（可选）
├── database/
│   └── migrations/              # 迁移文件（可选）
└── resources/
    ├── views/                   # 视图文件（可选）
    └── assets/                  # 静态资源（可选）
```

### 步骤 2: 创建 composer.json

```json
{
    "name": "my-vendor/my-plugin",
    "description": "My awesome plugin",
    "version": "1.0.0",
    "type": "library",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "siaoynli/laravel-plugins": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\MyPlugin\\": "src/"
        }
    },
    "extra": {
        "plugin": {
            "class": "MyVendor\\MyPlugin\\MyPluginPlugin"
        }
    }
}
```

> `extra.plugin.class` 告诉插件系统哪个类是插件入口。如果不配置，系统会尝试根据 PSR-4 命名空间自动推断。

### 步骤 3: 创建插件主类

```php
<?php

namespace MyVendor\MyPlugin;

use Siaoynli\Plugins\AbstractPlugin;

class MyPluginPlugin extends AbstractPlugin
{
    /**
     * register() — 仅做容器绑定和配置合并
     * 默认实现已自动合并 config/plugin.php 并注册 src/Providers/ 下的服务提供者
     */
    public function register(): void
    {
        parent::register();

        // 绑定你自己的服务
        // $this->app->singleton(MyService::class);
    }

    /**
     * boot() — 所有服务提供者已注册，可安全使用任何服务
     */
    public function boot(): void
    {
        // 注册事件监听、中间件等
        // Event::listen(UserCreated::class, SendWelcomeEmail::class);
    }

    /**
     * registerRoutes() — 路由注册（默认已实现，通常无需覆写）
     * 自动加载 routes/*.php，应用 route_prefix 和 middleware
     */
}
```

> **关键变化**：`register()` 和 `boot()` 现在是分离的两个阶段。`register()` 只做容器绑定，`boot()` 做需要依赖其他服务的初始化。

### 步骤 4: 创建配置文件

创建 `config/plugin.php`：

```php
<?php

return [
    'enabled' => true,
    'route_prefix' => 'my-plugin',
    'middleware' => ['api'],
];
```

### 步骤 5: 创建路由

创建 `routes/api.php`：

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'plugin' => 'my-plugin',
        'status' => 'ok',
    ]);
});
```

路由会自动包裹在 `Route::group` 中，应用配置的前缀和中间件。访问路径为 `/my-plugin/status`。

### 步骤 6: 测试插件

```bash
# 清除缓存并重新发现
php artisan plugin:clear
php artisan plugin:list

# 测试路由
curl http://localhost:8000/my-plugin/status
# {"plugin":"my-plugin","status":"ok"}
```

## 🔍 插件发现与缓存

### 三级发现来源（按优先级）

| 优先级 | 来源 | 说明 |
|--------|------|------|
| 1 (最高) | `config/plugins.php` 的 `plugins` 数组 | 手动注册，不会被自动发现覆盖 |
| 2 | `vendor/composer/installed.json` | Composer 安装的包，需声明 `extra.plugin.class` |
| 3 (最低) | `packages/` 目录扫描 | 本地开发包，最多扫描 2 层目录 |

### Manifest 缓存

插件发现结果会缓存到 `bootstrap/cache/plugins.php`：

```php
// bootstrap/cache/plugins.php
return [
    'my-vendor/my-plugin' => [
        'class' => 'MyVendor\\MyPlugin\\MyPluginPlugin',
        'source' => 'local',
    ],
    'siaoynli/phone-auth-plugin' => [
        'class' => 'Siaoynli\\PhoneAuth\\PhoneAuthPlugin',
        'source' => 'vendor',
    ],
];
```

**缓存命中时**：直接 `require` 缓存文件，零文件系统扫描，单次请求开销约 1ms。

```bash
# 构建缓存
php artisan plugin:cache

# 清除缓存（开发时常用）
php artisan plugin:clear
```

> **生产环境建议**：部署时执行 `plugin:cache`，开发时关闭缓存或将 `PLUGINS_SCAN_LOCAL` 保持开启。

## 🔄 插件生命周期

插件系统严格遵循 Laravel 的 ServiceProvider 生命周期：

```
ServiceProvider::register()
  └── 绑定 PluginManifest / PluginDiscovery / PluginRegistry / PluginPublisher 单例

ServiceProvider::boot()
  │
  ├── 1. Discovery  — 发现插件（有缓存则跳过扫描）
  │
  ├── 2. Register   — 实例化插件 → 调用 register()
  │     ├── 合并配置（应用配置优先）
  │     ├── 自动注册 src/Providers/ 下的服务提供者
  │     └── 分发 PluginRegistered 事件
  │
  ├── 3. Boot       — 调用 boot() + registerRoutes()
  │     ├── 子类自定义启动逻辑
  │     ├── 加载 routes/*.php（尊重路由缓存）
  │     └── 分发 PluginBooted 事件
  │
  └── 4. Publish    — 声明式注册可发布资源（不拷贝文件）
```

### 监听生命周期事件

```php
// AppServiceProvider::boot()
use Siaoynli\Plugins\Events\PluginRegistered;
use Siaoynli\Plugins\Events\PluginBooted;

Event::listen(function (PluginRegistered $event) {
    Log::info("插件已注册: {$event->plugin->getName()}");
});

Event::listen(function (PluginBooted $event) {
    Log::info("插件已启动: {$event->plugin->getName()}");
});
```

## 📦 资源发布

资源发布采用 Laravel 标准的**声明式注册**模式：

- `boot()` 时只**注册**哪些资源可以发布，**不拷贝文件**
- 用户通过 `vendor:publish` 命令**手动触发**发布

### 发布标签

| 标签 | 来源 | 目标 |
|------|------|------|
| `{tag}-migrations` | `database/migrations/` | `database/migrations/` |
| `{tag}-config` | `config/plugin.php` | `config/plugins/{slug-name}.php` |
| `{tag}-views` | `resources/views/` | `resources/views/vendor/{slug-name}/` |
| `{tag}-assets` | `resources/assets/` | `public/plugins/{slug-name}/` |

其中 `{tag}` 为插件包名中 `/` 替换为 `-`，例如 `siaoynli/phone-auth-plugin` → `siaoynli-phone-auth-plugin`。

### 发布命令

```bash
# 查看某个插件的可用标签
php artisan plugin:publish siaoynli/phone-auth-plugin

# 发布指定类型
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-config
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-migrations

# 发布某个插件的所有资源
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-config \
                           --tag=siaoynli-phone-auth-plugin-migrations \
                           --tag=siaoynli-phone-auth-plugin-views

# 发布所有插件的所有资源
php artisan vendor:publish --provider="Siaoynli\Plugins\Providers\PluginServiceProvider"

# 强制覆盖已有文件
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-config --force
```

## ⚙️ 配置参考

发布配置文件后，可在 `config/plugins.php` 中调整：

```php
return [

    // 插件系统总开关
    'enabled' => env('PLUGINS_ENABLED', true),

    // 插件发现配置
    'discovery' => [
        'scan_local_packages' => env('PLUGINS_SCAN_LOCAL', true),  // 是否扫描 packages/ 目录
        'packages_path' => 'packages',                             // 本地包目录
    ],

    // Manifest 缓存配置
    'cache' => [
        'enabled' => env('PLUGIN_CACHE', true),
        'path' => null,  // null = bootstrap/cache/plugins.php
    ],

    // 手动注册插件（最高优先级）
    'plugins' => [
        // 'vendor/package-name' => \Vendor\Package\PluginClass::class,
    ],
];
```

### 插件级配置

插件的配置统一通过 `config/plugins/{slug-name}.php` 管理（`{slug-name}` 为包名中 `/` 替换为 `-`）。

**获取配置文件**：通过 `vendor:publish` 发布插件配置：

```bash
php artisan vendor:publish --tag=my-vendor-my-plugin-config
```

这会将插件自带的 `config/plugin.php` 复制到 `config/plugins/my-vendor-my-plugin.php`。

**编辑配置**：

```php
// config/plugins/my-vendor-my-plugin.php
return [
    'enabled' => true,               // 是否启用
    'route_prefix' => 'my-plugin',   // 路由前缀
    'middleware' => ['api'],          // 路由中间件

    // 插件自定义的其他配置项...
];
```

**配置优先级**：`config/plugins/{slug-name}.php`（应用级） > 插件自带 `config/plugin.php`（默认值）。`register()` 阶段会自动将两者合并，应用级配置覆盖同名键。

## 🔧 API 参考

### PluginInterface（契约）

所有插件必须实现 7 个核心方法：

```php
interface PluginInterface
{
    public function getName(): string;         // 插件名称
    public function getVersion(): string;      // 版本号
    public function getDescription(): string;  // 描述
    public function isEnabled(): bool;         // 是否启用
    public function register(): void;          // 注册阶段（容器绑定）
    public function boot(): void;              // 启动阶段（路由、事件等）
    public function registerRoutes(): void;    // 注册路由
}
```

### AbstractPlugin（基类）

继承 `AbstractPlugin` 可获得所有默认实现，通常只需覆写 `boot()`：

```php
use Siaoynli\Plugins\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    // register() — 已实现：配置合并 + 服务提供者自动发现
    // boot()     — 空实现，子类按需覆写
    // registerRoutes() — 已实现：自动加载 routes/*.php

    // 可用的公共方法：
    // $this->getBasePath()         → 插件根目录
    // $this->getConfig(?$key)      → 获取配置
    // $this->getRoutePrefix()      → 路由前缀
    // $this->getMiddleware()        → 中间件列表
    // $this->getPluginNamespace()  → PSR-4 命名空间
}
```

### PluginRegistry（注册表）

推荐使用 `PluginRegistry` 直接操作插件实例：

```php
use Siaoynli\Plugins\Registry\PluginRegistry;

// 依赖注入
public function __construct(PluginRegistry $registry)
{
    // 获取所有插件
    $plugins = $registry->all();

    // 获取特定插件
    $plugin = $registry->get('vendor/plugin-name');

    // 检查是否存在
    $exists = $registry->has('vendor/plugin-name');

    // 获取摘要列表
    $list = $registry->list();

    // 插件数量
    $count = $registry->count();
}
```

### PluginManager（向后兼容门面）

`PluginManager` 保留为 `PluginRegistry` 的门面，旧代码无需修改：

```php
use Siaoynli\Plugins\PluginManager;

$manager = app(PluginManager::class);  // 或 app('plugin-manager')
$plugins = $manager->getPlugins();      // → $registry->all()
$plugin  = $manager->getPlugin($name);  // → $registry->get($name)
$exists  = $manager->hasPlugin($name);  // → $registry->has($name)
$list    = $manager->listPlugins();     // → $registry->list()
```

> **提示**：新代码建议直接注入 `PluginRegistry`。

## 💻 Artisan 命令

### `plugin:list`

列出所有已加载的插件。

```bash
php artisan plugin:list
```

### `plugin:publish`

显示插件的可用发布标签和使用示例。

```bash
# 查看所有插件的发布标签
php artisan plugin:publish

# 查看特定插件的发布标签
php artisan plugin:publish siaoynli/phone-auth-plugin
```

### `plugin:cache`

构建插件 manifest 缓存，生产环境推荐。

```bash
php artisan plugin:cache
```

### `plugin:clear`

清除 manifest 缓存。

```bash
php artisan plugin:clear
```

## ❓ 常见问题

### Q: 如何禁用某个插件？

在 `config/plugins/{slug-name}.php` 中设置 `enabled` 为 `false`：

```php
// config/plugins/siaoynli-phone-auth-plugin.php
return [
    'enabled' => false,
];
```

如果该文件不存在，先发布配置：

```bash
php artisan vendor:publish --tag=siaoynli-phone-auth-plugin-config
```
```

### Q: 如何自定义路由前缀和中间件？

在 `config/plugins/{slug-name}.php` 中配置：

```php
// config/plugins/my-vendor-my-plugin.php
return [
    'route_prefix' => 'custom-prefix',
    'middleware' => ['api', 'auth:api'],
];
```
```

### Q: 插件未被加载怎么办？

1. 清除缓存：`php artisan plugin:clear`
2. 检查 `config/plugins.php` 的 `plugins` 数组
3. 确认插件 `composer.json` 中有 `extra.plugin.class` 配置
4. 运行 `php artisan plugin:list` 查看加载状态
5. 检查日志 `storage/logs/laravel.log`

### Q: 开发时修改了插件但没生效？

```bash
php artisan plugin:clear    # 清除 manifest 缓存
php artisan config:clear    # 清除配置缓存
```

### Q: 路由缓存后插件路由消失了？

`AbstractPlugin::registerRoutes()` 已内置 `routesAreCached()` 检查。如果你自己覆写了 `registerRoutes()`，请确保加入同样的检查：

```php
public function registerRoutes(): void
{
    if (app()->routesAreCached()) return;
    // 你的路由注册逻辑...
}
```

### Q: 如何在插件中使用视图？

插件的 `resources/views/` 会被自动注册为命名空间视图：

```php
// 插件内
return view('siaoynli-phone-auth-plugin::login');

// 发布后用户可覆盖
// resources/views/vendor/siaoynli-phone-auth-plugin/login.blade.php
```

### Q: 如何监听其他插件的生命周期？

```php
use Siaoynli\Plugins\Events\PluginBooted;

Event::listen(function (PluginBooted $event) {
    if ($event->plugin->getName() === 'siaoynli/phone-auth-plugin') {
        // 在特定插件启动后执行逻辑
    }
});
```

### Q: 本地包和 Vendor 包有什么区别？

- **本地包**（`packages/`）— 用于开发阶段，便于快速迭代
- **Vendor 包**（`vendor/`）— 生产环境，通过 Composer 安装

两者使用相同的发现机制，区别仅在于目录位置。

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 🎯 Roadmap

- [ ] 插件市场/仓库
- [ ] 插件依赖管理与加载顺序
- [ ] 插件权限控制
- [ ] 前端插件支持
- [ ] 插件配置 UI
- [x] ~~插件事件系统~~ ✅ 已实现（PluginRegistered / PluginBooted）
- [x] ~~Manifest 缓存~~ ✅ 已实现（plugin:cache / plugin:clear）

## 📮 联系方式

如有问题或建议，请提交 [Issue](https://github.com/siaoynli/laravel-plugins/issues)。

---

**Made with ❤️ by Siaoynli**
