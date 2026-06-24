<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 插件系统总开关
    |--------------------------------------------------------------------------
    |
    | 设为 false 可完全禁用插件系统（ServiceProvider::boot 会直接返回）。
    |
    */
    'enabled' => env('PLUGINS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 插件发现配置
    |--------------------------------------------------------------------------
    |
    | scan_local_packages: 是否扫描 packages/ 目录中的本地插件包
    | packages_path:       本地包目录路径（相对于 base_path）
    |
    | 生产环境建议关闭 scan_local_packages 并依赖 manifest 缓存。
    |
    */
    'discovery' => [
        'scan_local_packages' => env('PLUGINS_SCAN_LOCAL', true),
        'packages_path' => 'packages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest 缓存配置
    |--------------------------------------------------------------------------
    |
    | 启用后，插件发现结果会缓存到 bootstrap/cache/plugins.php，
    | 生产环境无需每次请求都扫描文件系统。
    |
    | 使用 `php artisan plugin:cache` 构建缓存
    | 使用 `php artisan plugin:clear` 清除缓存
    |
    */
    'cache' => [
        'enabled' => env('PLUGIN_CACHE', true),
        'path' => null, // null 则使用默认路径：bootstrap/cache/plugins.php
    ],

    /*
    |--------------------------------------------------------------------------
    | 手动注册插件（最高优先级）
    |--------------------------------------------------------------------------
    |
    | 在此处显式注册插件，格式为 'vendor/package' => 'PluginClass'。
    | 手动注册的插件不会被自动发现覆盖。
    |
    | 示例：
    | 'plugins' => [
    |     'siaoynli/phone-auth-plugin' => \Siaoynli\PhoneAuth\PhoneAuthPlugin::class,
    | ],
    |
    */
    'plugins' => [
        //
    ],
];
