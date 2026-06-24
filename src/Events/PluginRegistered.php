<?php

namespace Siaoynli\Plugins\Events;

use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * 插件注册事件
 *
 * 当插件被注册到 Registry 时分发。
 * 可监听此事件实现跨插件通信或初始化逻辑。
 */
class PluginRegistered
{
    public function __construct(
        public readonly PluginInterface $plugin
    ) {}
}
