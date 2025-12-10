# Laravel Plugins

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel 11.0+](https://img.shields.io/badge/Laravel-11.0%2B-red.svg)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net)

一个强大而灵活的 Laravel 11 插件系统框架，支持插件的自动发现、注册、路由和资源发布。

## ✨ 主要特性

- 🔌 **完整的插件系统** - 定义、加载、注册、启动、路由和资源发布
- 🚀 **自动发现机制** - 支持三个来源的插件自动发现
- 📦 **三级优先级加载** - 配置文件、Composer 包、本地包
- 🛣️ **自动路由注册** - 插件路由自动注册和中间件配置
- 📁 **资源发布系统** - 支持迁移、配置、视图和资源文件发布
- ⚙️ **灵活的配置管理** - 每个插件独立配置启用状态和行为
- 📝 **详细的日志记录** - 完整的操作日志便于调试
- ✅ **插件启用/禁用控制** - 轻松启用或禁用任何插件
- 💻 **Artisan 命令行工具** - 两个强大的命令管理插件
- 📚 **完整的文档系统** - 详细的使用指南和 API 参考

## 📋 目录

- [安装](#安装)
- [快速开始](#快速开始)
- [功能说明](#功能说明)
- [使用指南](#使用指南)
- [API 参考](#api-参考)
- [常见问题](#常见问题)
- [许可证](#许可证)

## 📦 安装

### 前置要求

- PHP >= 8.2
- Laravel >= 11.0
- Composer

### 安装步骤

使用 Composer 安装：

```bash
composer require siaoynli/laravel-plugins
```

### 自动注册（Laravel 11）

Laravel 11 支持自动包发现，`PluginServiceProvider` 会自动注册到应用中。

如果需要手动注册，在 `config/app.php` 的 `providers` 数组中添加：

```php
'providers' => [
    // ...
    Siaoynli\Plugins\Providers\PluginServiceProvider::class,
],
```

### 发布配置文件（可选）

```bash
php artisan vendor:publish --provider="Siaoynli\Plugins\Providers\PluginServiceProvider"
```

这会在 `config/` 目录下生成 `app-plugins.php` 配置文件。

## 🚀 快速开始

### 1. 查看已加载的插件

```bash
php artisan plugin:list
```

输出示例：

```
✅ 1 plugin(s) loaded:

┌────────────────────────┬──────────────┬─────────┬──────────────────┬─────────┬──────────────┐
│ Package                │ Display Name │ Version │ Description      │ Enabled │ Route Prefix │
├────────────────────────┼──────────────┼─────────┼──────────────────┼─────────┼──────────────┤
│ my-vendor/my-plugin    │ My Plugin    │ 1.0.0   │ This is my..      │ ✓       │ my-plugin    │
└────────────────────────┴──────────────┴─────────┴──────────────────┴─────────┴──────────────┘

Summary:
  • Total plugins: 1
  • Enabled: 1
  • Disabled: 0
```

### 2. 发布插件资源

```bash
# 发布所有插件的资源
php artisan plugin:publish

# 发布特定插件的资源
php artisan plugin:publish vendor/my-plugin
```

### 3. 创建您的第一个插件

#### 步骤 1: 创建插件目录

```bash
mkdir -p packages/my-vendor/my-plugin
cd packages/my-vendor/my-plugin
```

#### 步骤 2: 创建 composer.json

```json
{
  "name": "my-vendor/my-plugin",
  "description": "My first awesome plugin",
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
      "class": "MyVendor\\MyPlugin\\MyPlugin"
    }
  }
}
```

#### 步骤 3: 创建插件主类

创建 `src/MyPlugin.php`：

```php
<?php

namespace MyVendor\MyPlugin;

use Siaoynli\Plugins\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    public function getName(): string
    {
        return 'My Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'This is my first awesome plugin';
    }
}
```

#### 步骤 4: 创建配置文件

创建 `config/plugin.php`：

```php
<?php

return [
    'enabled' => true,
    'route_prefix' => 'my-plugin',
    'middleware' => ['api'],
];
```

#### 步骤 5: 创建路由文件（可选）

创建 `routes/api.php`：

```php
<?php

Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'My plugin is working!'
    ]);
});
```

#### 步骤 6: 完整的目录结构

```
packages/my-vendor/my-plugin/
├── composer.json
├── config/
│   └── plugin.php
├── src/
│   ├── MyPlugin.php
│   ├── Providers/
│   │   └── MyPluginServiceProvider.php (可选)
│   └── Http/
│       └── Controllers/ (可选)
├── routes/
│   └── api.php (可选)
├── database/
│   └── migrations/ (可选)
└── resources/
    ├── views/ (可选)
    └── assets/ (可选)
```

### 4. 测试您的插件

```bash
# 1. 列出插件
php artisan plugin:list

# 输出应该显示您的插件已加载

# 2. 发布资源
php artisan plugin:publish

# 3. 测试 API
curl http://localhost:8000/api/my-plugin/status

# 响应示例:
# {"status":"ok","message":"My plugin is working!"}
```

## 📖 功能说明

### 插件加载优先级

系统从三个来源加载插件，按优先级顺序：

#### 1️⃣ 配置文件（优先级最高）

编辑 `config/app-plugins.php`：

```php
return [
    'my-vendor/my-plugin' => 'MyVendor\MyPlugin\MyPlugin',
    'another-vendor/plugin' => 'AnotherVendor\Plugin\Plugin',
];
```

**优势**:

- 可以明确指定加载哪些插件
- 可以禁用自动发现的插件
- 便于生产环境控制

#### 2️⃣ Vendor 包（优先级中）

插件的 `composer.json` 中配置：

```json
{
  "extra": {
    "plugin": {
      "class": "Vendor\\Plugin\\PluginClass"
    }
  }
}
```

**优势**:

- 自动发现，无需手动配置
- 通过 Composer 安装的包自动识别

#### 3️⃣ 本地包（优先级最低）

放在 `packages/` 目录中：

```
packages/
├── my-vendor/
│   └── my-plugin/
│       └── composer.json
└── another-vendor/
    └── plugin/
        └── composer.json
```

**优势**:

- 便于本地开发
- 支持多层目录结构（最多 2 层）

### 插件生命周期

```
1️⃣ 发现 (Discovery)
   ├─ 从 config/app-plugins.php 读取
   ├─ 从 vendor 自动发现
   └─ 从 packages 目录扫描

2️⃣ 注册 (Registration)
   ├─ 验证类是否存在
   ├─ 检查接口实现
   └─ 检查启用状态

3️⃣ 启动 (Boot)
   ├─ 调用 register() 方法
   ├─ 加载配置文件
   └─ 注册服务提供者

4️⃣ 路由 (Routes)
   └─ 注册所有路由

5️⃣ 资源 (Assets) - 仅在 console
   └─ 发布迁移、配置、视图、资源
```

### 资源发布

支持发布四种资源类型：

```
插件根目录
├── database/migrations/
│   └── *.php  →  database/migrations/
│
├── config/plugin.php
│   └── →  config/plugins/{plugin-name}.php
│
├── resources/views/
│   └── →  resources/views/plugins/{plugin-name}/
│
└── resources/assets/
    └── →  public/plugins/{plugin-name}/
```

## 🔧 API 参考

### PluginManager

#### 获取插件管理器

```php
use Siaoynli\Plugins\PluginManager;

// 方式 1: 依赖注入
public function __construct(PluginManager $manager)
{
    $this->manager = $manager;
}

// 方式 2: 容器
$manager = app(PluginManager::class);
$manager = app('plugin-manager');
```

#### 常用方法

```php
// 获取所有插件
$plugins = $manager->getPlugins();

// 获取特定插件
$plugin = $manager->getPlugin('vendor/plugin-name');

// 检查插件是否存在
$exists = $manager->hasPlugin('vendor/plugin-name');

// 获取所有插件信息（数组形式）
$list = $manager->listPlugins();

// 发布单个插件的资源
$success = $manager->publishPlugin('vendor/plugin-name');
```

### AbstractPlugin

所有插件都应该继承此类：

```php
use Siaoynli\Plugins\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    // 必须实现的方法
    public function getName(): string { }
    public function getVersion(): string { }
    public function getDescription(): string { }

    // 可选的方法
    public function loadConfig(): void;
    public function register(): void { }
    public function registerRoutes(): void { }
    public function publishAssets(): void { }
}
```

#### 可用的方法和属性

```php
// 获取基础路径
$basePath = $this->getBasePath();

// 获取配置
$config = $this->getConfig();
$value = $this->getConfig('key');

// 判断是否启用
$enabled = $this->isEnabled();

// 获取路由前缀
$prefix = $this->getRoutePrefix();

// 获取中间件
$middleware = $this->getMiddleware();

// 获取插件名称
$name = $this->getPluginName();

// 获取插件命名空间
$namespace = $this->getPluginNamespace();
```

## 💻 Artisan 命令

### plugin:list

列出所有已加载的插件及详细信息。

```bash
php artisan plugin:list
```

**输出**:

- 插件包名
- 显示名称
- 版本
- 描述
- 启用状态
- 路由前缀
- 统计信息

### plugin:publish

发布插件资源到主应用。

```bash
# 发布所有插件
php artisan plugin:publish

# 发布特定插件
php artisan plugin:publish vendor/plugin-name

# 显示详细信息
php artisan plugin:publish vendor/plugin-name -v

# 强制覆盖
php artisan plugin:publish vendor/plugin-name --force
```

**功能**:

- 支持单个或全部发布
- 进度条显示
- 详细的操作报告
- 强制覆盖选项

## ❓ 常见问题

### Q: 如何禁用某个插件？

A: 在插件的 `config/plugin.php` 中设置：

```php
return [
    'enabled' => false,
];
```

或在应用的 `config/plugins/{plugin-name}.php` 中修改相同的设置。

### Q: 如何自定义路由前缀？

A: 在插件的 `config/plugin.php` 中设置：

```php
return [
    'route_prefix' => 'custom-prefix',
];
```

### Q: 如何添加自定义中间件？

A: 在插件的 `config/plugin.php` 中设置：

```php
return [
    'middleware' => ['api', 'auth:api', 'custom-middleware'],
];
```

### Q: 插件未被加载怎么办？

A: 按以下步骤排查：

1. 检查 `config/app-plugins.php` 配置
2. 确认插件的 `composer.json` 配置正确
3. 验证命名空间与代码一致
4. 查看日志文件 `storage/logs/laravel.log`
5. 运行 `php artisan plugin:list` 查看加载状态

### Q: 如何访问插件的路由？

A: 路由自动注册，访问方式：

```
GET /api/{route_prefix}/endpoint
```

例如，如果路由前缀是 `my-plugin`，路由文件中定义了 `/users`：

```
GET /api/my-plugin/users
```

### Q: 如何在插件中访问主应用的配置？

A: 使用 Laravel 的 config 辅助函数：

```php
$appConfig = config('app.name');
$pluginConfig = config('plugins.my-vendor-my-plugin');
```

### Q: 本地包和 Vendor 包有什么区别？

A:

- **本地包** (`packages/`) - 用于开发阶段，便于快速迭代
- **Vendor 包** (`vendor/`) - 生产环境，通过 Composer 安装

### Q: 可以在插件中创建数据库表吗？

A: 可以，使用迁移文件：

```
插件/database/migrations/
└── 2024_01_01_000000_create_tables.php
```

发布后会自动复制到应用的 `database/migrations/` 目录。

### Q: 如何在插件中使用 Laravel 的 Service Provider？

A: 将 Service Provider 放在 `src/Providers/` 目录中，系统会自动注册：

```
插件/src/Providers/
└── MyPluginServiceProvider.php
```

### Q: 插件之间可以互相依赖吗？

A: 建议避免直接依赖，但可以通过事件或服务容器解耦。

## 📚 更多资源

- [安装和快速开始](INSTALLATION.md) - 详细的安装步骤
- [系统架构说明](ARCHITECTURE.md) - 深入理解系统设计
- [命令详解](COMMANDS.md) - Artisan 命令的详细说明
- [快速参考卡片](QUICK_REFERENCE.md) - 常用命令速查表
- [完整文件指南](COMPLETE_GUIDE.md) - 项目文件结构说明

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 👨‍💻 作者

Siaoynli

## 🎯 Roadmap

- [ ] 插件市场/仓库
- [ ] 插件依赖管理
- [ ] 插件版本管理
- [ ] 插件事件系统
- [ ] 插件权限控制
- [ ] 前端插件支持
- [ ] 插件配置 UI

## 📮 联系方式

如有问题或建议，请提交 [Issue](https://github.com/siaoynli/laravel-plugins/issues)。

---

**Made with ❤️ by Siaoynli**
