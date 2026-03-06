<?php

namespace TelescopeAI\AutoDebug;

use Illuminate\Support\ServiceProvider;

class AutoDebugServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/autodebug.php',
            'autodebug'
        );

        // Register singletons
        $this->app->singleton(Services\ExceptionAnalyzer::class);
        $this->app->singleton(Services\AIService::class);
        $this->app->singleton(Services\FixGenerator::class);
        $this->app->singleton(Services\GitHubPRService::class);
        $this->app->singleton(Services\NotificationService::class);
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerMigrations();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register the package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'autodebug');
    }

    /**
     * Register the package Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\AutoDebugAnalyzeCommand::class,
                Commands\InstallCommand::class,
            ]);
        }
    }

    /**
     * Register the package migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/autodebug.php' => config_path('autodebug.php'),
            ], 'autodebug-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'autodebug-migrations');

            // Views (for customization)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/autodebug'),
            ], 'autodebug-views');

            // Everything
            $this->publishes([
                __DIR__ . '/../config/autodebug.php' => config_path('autodebug.php'),
                __DIR__ . '/../database/migrations'  => database_path('migrations'),
                __DIR__ . '/../resources/views'       => resource_path('views/vendor/autodebug'),
            ], 'autodebug');
        }
    }
}
