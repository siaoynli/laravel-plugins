<?php

namespace Siaoynli\Plugins\Events;

use Siaoynli\Plugins\Contracts\PluginInterface;

/**
 * 插件启动事件
 *
 * 当插件完成 boot() 和 registerRoutes() 后分发。
 * 可监听此事件在插件完全就绪后执行逻辑。
 */
class PluginBooted
{
    public function __construct(
        public readonly PluginInterface $plugin
    ) {}
}
