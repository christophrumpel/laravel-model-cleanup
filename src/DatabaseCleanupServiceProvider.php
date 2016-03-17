<?php

namespace Spatie\DatabaseCleanup;

use Illuminate\Support\ServiceProvider;

class DatabaseCleanupServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laravel-database-cleanup.php' => config_path('laravel-database-cleanup.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-database-cleanup.php', 'laravel-database-cleanup');

        $this->app->bind('command.clean:models', CleanUpModelsCommand::class);

        $this->commands([
            'command.clean:models',
        ]);
    }
}
