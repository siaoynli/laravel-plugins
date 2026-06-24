<?php

namespace Siaoynli\Plugins\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\PluginManifest;

class PluginClearCommand extends Command
{
    protected $signature = 'plugin:clear';
    protected $description = '清除插件 manifest 缓存';

    public function handle(): int
    {
        $manifest = $this->laravel->make(PluginManifest::class);

        if ($manifest->isCached()) {
            $path = $manifest->getManifestPath();
            $manifest->clear();
            $this->info("✅ Manifest cache cleared");
            $this->line("   → {$path}");
        } else {
            $this->info('No manifest cache to clear');
        }

        return Command::SUCCESS;
    }
}
