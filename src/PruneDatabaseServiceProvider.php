<?php

namespace Bunce\PruneDatabase;

use Illuminate\Support\ServiceProvider;

class PruneDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configure();
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
    }

    /**
     * Setup the configuration for Database Pruning.
     */
    protected function configure(): static
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/prune-database.php', 'prune-database'
        );

        return $this;
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): static
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/prune-database.php' => $this->app->configPath('prune-database.php'),
            ], 'prune-database-config');
        }

        return $this;
    }
}
