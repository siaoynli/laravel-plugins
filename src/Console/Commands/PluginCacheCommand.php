<?php

namespace Siaoynli\Plugins\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\Discovery\PluginDiscovery;
use Siaoynli\Plugins\PluginManifest;

class PluginCacheCommand extends Command
{
    protected $signature = 'plugin:cache';
    protected $description = '构建插件 manifest 缓存（bootstrap/cache/plugins.php）';

    public function handle(): int
    {
        $manifest = $this->laravel->make(PluginManifest::class);

        // 先清除旧缓存
        $manifest->clear();

        // 重新发现并写入缓存
        $discovery = $this->laravel->make(PluginDiscovery::class);
        $plugins = $discovery->discover($manifest);

        $count = count($plugins);
        $this->info("✅ Manifest cached with {$count} plugin(s)");
        $this->line("   → " . $manifest->getManifestPath());

        if ($count > 0) {
            $this->line('');
            $this->table(
                ['Package', 'Class', 'Source'],
                collect($plugins)->map(fn($data, $name) => [
                    $name,
                    $data['class'] ?? 'N/A',
                    $data['source'] ?? 'N/A',
                ])->values()->all()
            );
        }

        return Command::SUCCESS;
    }
}
