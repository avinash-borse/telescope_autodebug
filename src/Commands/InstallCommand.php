<?php

namespace TelescopeAI\AutoDebug\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'autodebug:install';

    protected $description = 'Install the AutoDebug package — publish config, run migration, and display setup instructions';

    public function handle(): int
    {
        $this->info('');
        $this->info('  🔭 TelescopeAI AutoDebug — Installation');
        $this->info('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Step 1: Publish config
        $this->info('  📦 Step 1: Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'autodebug-config',
        ]);
        $this->info('  ✅ Config published to config/autodebug.php');
        $this->newLine();

        // Step 2: Run migration
        if ($this->confirm('  Run database migration now?', true)) {
            $this->info('  📦 Step 2: Running migration...');
            $this->call('migrate');
            $this->info('  ✅ Migration complete — auto_debug_entries table created');
        } else {
            $this->comment('  ⏭️  Skipped migration. Run `php artisan migrate` when ready.');
        }
        $this->newLine();

        // Step 3: Check Telescope
        $this->info('  📦 Step 3: Checking Telescope...');
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            $this->info('  ✅ Laravel Telescope is installed');
        } else {
            $this->warn('  ⚠️  Laravel Telescope is NOT installed!');
            $this->comment('     AutoDebug requires Telescope to capture exceptions.');
            $this->comment('     Install it: composer require laravel/telescope');
        }
        $this->newLine();

        // Step 4: Show env template
        $this->info('  📦 Step 4: Environment Configuration');
        $this->comment('  Add these to your .env file:');
        $this->newLine();

        $envLines = [
            '# ── AutoDebug ─────────────────────────────────',
            'AUTODEBUG_ENABLED=true',
            'AUTODEBUG_AI_PROVIDER=openai',
            'AUTODEBUG_OPENAI_API_KEY=sk-your-key-here',
            '',
            '# GitHub (for auto PR creation)',
            'AUTODEBUG_GITHUB_ENABLED=true',
            'AUTODEBUG_GITHUB_TOKEN=ghp_your-token',
            'AUTODEBUG_GITHUB_OWNER=your-org',
            'AUTODEBUG_GITHUB_REPO=your-repo',
            'AUTODEBUG_GITHUB_BASE_BRANCH=main',
            '',
            '# Optional: Slack notifications',
            '# AUTODEBUG_NOTIFICATION_CHANNELS=database,slack',
            '# AUTODEBUG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...',
        ];

        foreach ($envLines as $line) {
            $this->line("    {$line}");
        }
        $this->newLine();

        // Step 5: Scheduler
        $this->info('  📦 Step 5: Scheduler Setup');
        $this->comment('  Add to your scheduler (routes/console.php or Kernel.php):');
        $this->newLine();
        $this->line("    Schedule::command('autodebug:analyze')");
        $this->line("        ->everyFiveMinutes()");
        $this->line("        ->withoutOverlapping()");
        $this->line("        ->runInBackground();");
        $this->newLine();

        // Done
        $this->newLine();
        $this->info('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  🎉 Installation complete!');
        $this->newLine();
        $this->comment('  Quick commands:');
        $this->line('    php artisan autodebug:analyze           # Run analysis');
        $this->line('    php artisan autodebug:analyze --dry-run # Preview only');
        $this->comment('  Dashboard: /auto-debug');
        $this->newLine();

        return self::SUCCESS;
    }
}
