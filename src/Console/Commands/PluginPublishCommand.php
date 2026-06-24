<?php

namespace Siaoynli\Plugins\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\Registry\PluginRegistry;

class PluginPublishCommand extends Command
{
    protected $signature = 'plugin:publish {plugin? : 插件包名（可选，留空则提示使用 vendor:publish）}';
    protected $description = '提示使用 vendor:publish 发布插件资源';

    public function handle(): int
    {
        $registry = $this->laravel->make(PluginRegistry::class);

        if ($registry->count() === 0) {
            $this->warn('No plugins loaded.');
            return Command::FAILURE;
        }

        $pluginName = $this->argument('plugin');

        if ($pluginName) {
            return $this->showPluginTags($registry, $pluginName);
        }

        return $this->showAllTags($registry);
    }

    /**
     * 显示指定插件的可用发布标签
     */
    protected function showPluginTags(PluginRegistry $registry, string $pluginName): int
    {
        $plugin = $registry->get($pluginName);

        if (!$plugin) {
            $this->error("Plugin not found: {$pluginName}");
            $this->line('');
            $this->showAllTags($registry);
            return Command::FAILURE;
        }

        $tag = str_replace('/', '-', $pluginName);

        $this->info("Publishable resources for: {$pluginName}");
        $this->line('');
        $this->line("  php artisan vendor:publish --tag={$tag}-config");
        $this->line("  php artisan vendor:publish --tag={$tag}-migrations");
        $this->line("  php artisan vendor:publish --tag={$tag}-views");
        $this->line("  php artisan vendor:publish --tag={$tag}-assets");
        $this->line('');
        $this->line("  php artisan vendor:publish --tag={$tag}-config --tag={$tag}-migrations  (多个标签)");

        return Command::SUCCESS;
    }

    /**
     * 显示所有插件的发布标签
     */
    protected function showAllTags(PluginRegistry $registry): int
    {
        $this->info('Use vendor:publish to publish plugin resources:');
        $this->line('');

        foreach ($registry->all() as $name => $plugin) {
            $tag = str_replace('/', '-', $name);
            $this->line("  <info>{$name}</info>");
            $this->line("    vendor:publish --tag={$tag}-config");
            $this->line("    vendor:publish --tag={$tag}-migrations");
            $this->line("    vendor:publish --tag={$tag}-views");
            $this->line("    vendor:publish --tag={$tag}-assets");
            $this->line('');
        }

        $this->line('  Publish all: vendor:publish --provider="Siaoynli\\\\Plugins\\\\Providers\\\\PluginServiceProvider"');

        return Command::SUCCESS;
    }
}
