<?php

namespace Siaoynli\Plugins\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\Registry\PluginRegistry;

class PluginListCommand extends Command
{
    protected $signature = 'plugin:list';
    protected $description = '列出所有已加载的插件';

    public function handle(): int
    {
        $registry = $this->laravel->make(PluginRegistry::class);
        $plugins = $registry->list();

        if (empty($plugins)) {
            $this->warn('No plugins loaded.');
            $this->line('');
            $this->line('Suggestions:');
            $this->line('  • Check config/plugins.php for manual plugin registration');
            $this->line('  • Ensure plugin packages have "extra.plugin.class" in composer.json');
            $this->line('  • Run "php artisan plugin:cache" to rebuild the manifest');
            $this->line('  • Run "php artisan plugin:clear" to clear stale cache');
            return Command::FAILURE;
        }

        $this->info(count($plugins) . ' plugin(s) loaded:');
        $this->line('');

        $this->table(
            ['Package', 'Name', 'Version', 'Enabled'],
            array_map(fn($p) => [
                $p['package_name'],
                $p['display_name'],
                $p['version'],
                $p['enabled'] ? '✓' : '✗',
            ], $plugins)
        );

        return Command::SUCCESS;
    }
}
