<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Siaoynli\Plugins\PluginManager;

class PluginPublishCommand extends Command
{
  protected $signature = 'app:plugin-publish {plugin? : 插件包名称（可选，留空则发布所有）}';

  protected $description = '发布插件的资源（迁移、配置、视图、静态资源等）';

  public function handle()
  {
    $this->line('');
    $this->info('╔════════════════════════════════════════╗');
    $this->info('║   Plugin Asset Publishing Command      ║');
    $this->info('╚════════════════════════════════════════╝');
    $this->line('');

    try {
      $manager = app(PluginManager::class);
      $pluginName = $this->argument('plugin');

      if (empty($manager->getPlugins())) {
        $this->warn('⚠️  No plugins loaded. Please check your plugin configuration.');
        return Command::FAILURE;
      }

      if ($pluginName) {
        return $this->publishSinglePlugin($manager, $pluginName);
      } else {
        return $this->publishAllPlugins($manager);
      }
    } catch (\Exception $e) {
      $this->newLine();
      $this->error('❌ Error: ' . $e->getMessage());
      if ($this->getOutput()->isVerbose()) {
        $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
        $this->error('Trace: ' . $e->getTraceAsString());
      }
      return Command::FAILURE;
    }
  }

  /**
   * 发布单个插件的资源
   */
  protected function publishSinglePlugin(PluginManager $manager, string $pluginName): int
  {
    $plugin = $manager->getPlugin($pluginName);

    if (!$plugin) {
      $this->newLine();
      $this->error("❌ Plugin not found: {$pluginName}");
      $this->line('');
      $this->info('Available plugins:');
      $this->listAvailablePlugins($manager);
      return Command::FAILURE;
    }

    $this->info("Publishing plugin: {$pluginName}");
    $this->newLine();

    if ($manager->publishPlugin($pluginName)) {
      $this->newLine();
      $this->info("✅ Plugin '{$pluginName}' assets published successfully");
      $this->line('');
      return Command::SUCCESS;
    } else {
      $this->newLine();
      $this->error("❌ Failed to publish plugin: {$pluginName}");
      return Command::FAILURE;
    }
  }

  /**
   * 发布所有插件的资源
   */
  protected function publishAllPlugins(PluginManager $manager): int
  {
    $plugins = $manager->getPlugins();
    $count = count($plugins);

    $this->info("Publishing assets for {$count} plugin(s)...");
    $this->newLine();

    $progressBar = $this->output->createProgressBar($count);
    $progressBar->setFormat(
      "%message%\n %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%"
    );
    $progressBar->setMessage('Initializing...');
    $progressBar->start();

    foreach ($plugins as $name => $plugin) {
      $progressBar->setMessage("Publishing: {$name}");

      try {
        $manager->publishPlugin($name);
      } catch (\Exception $e) {
        if ($this->getOutput()->isVerbose()) {
          $progressBar->clear();
          $this->warn("  ⚠️  Error publishing {$name}: " . $e->getMessage());
          $progressBar->display();
        }
      }

      $progressBar->advance();
    }

    $progressBar->finish();
    $this->newLine(2);

    $this->info("✅ All plugins published successfully");
    $this->line('');

    return Command::SUCCESS;
  }

  /**
   * 列出可用的插件
   */
  protected function listAvailablePlugins(PluginManager $manager): void
  {
    $plugins = $manager->listPlugins();

    if (empty($plugins)) {
      $this->warn('  No plugins available');
      return;
    }

    $headers = ['Package', 'Version', 'Enabled'];
    $rows = array_map(function ($plugin) {
      return [
        $plugin['package_name'],
        $plugin['version'],
        $plugin['enabled'] ? '✓' : '✗',
      ];
    }, $plugins);

    $this->table($headers, $rows);
  }
}
