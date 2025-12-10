<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\PluginManager;

class PluginListCommand extends Command
{
  protected $signature = 'app:plugin-list';
  protected $description = 'List all loaded plugins';

  public function handle()
  {
    $this->info('Retrieving plugins...');

    try {
      $manager = app(PluginManager::class);

      // 不需要再调用 loadPlugins()，因为 ServiceProvider::boot() 已经调用过了
      $plugins = $manager->listPlugins();

      if (empty($plugins)) {
        $this->warn('❌ No plugins found!');
        $this->line('');
        $this->info('Debugging Information:');

        // 显示配置
        $configPlugins = config('app-plugins', []);
        $this->line("  • Config plugins configured: " . count($configPlugins));
        if (!empty($configPlugins)) {
          foreach ($configPlugins as $name => $class) {
            $this->line("    - {$name} => {$class}");
          }
        }

        // 显示 packages 目录
        $packagesPath = base_path('packages');
        if (is_dir($packagesPath)) {
          $dirs = array_diff(scandir($packagesPath), ['.', '..']);
          $this->line("  • Packages directory exists with " . count($dirs) . " items:");
          foreach ($dirs as $dir) {
            $this->line("    - {$dir}");
          }
        } else {
          $this->warn("  • packages/ directory not found at: {$packagesPath}");
        }

        // 提示查看日志
        $this->line('');
        $this->info('📋 Check logs for detailed information:');
        $this->line('  tail -f storage/logs/laravel.log');

        return Command::FAILURE;
      }

      // 显示插件列表
      $this->info('✅ ' . count($plugins) . ' plugin(s) loaded:');
      $this->line('');

      $headers = ['Package', 'Name', 'Version', 'Enabled', 'Route Prefix'];
      $rows = array_map(function ($plugin) {
        return [
          $plugin['package_name'],
          $plugin['display_name'],
          $plugin['version'],
          $plugin['enabled'] ? '✓' : '✗',
          $plugin['route_prefix'] ?? '-',
        ];
      }, $plugins);

      $this->table($headers, $rows);

      return Command::SUCCESS;
    } catch (\Exception $e) {
      $this->error('Error: ' . $e->getMessage());
      $this->error('File: ' . $e->getFile());
      $this->error('Line: ' . $e->getLine());

      return Command::FAILURE;
    }
  }
}
