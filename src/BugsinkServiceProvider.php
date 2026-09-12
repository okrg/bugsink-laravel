<?php

namespace Okrg\BugsinkLaravel;

use Illuminate\Support\ServiceProvider;
use Okrg\BugsinkLaravel\Console\Commands\BugsinkReadCommand;

class BugsinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bugsink.php', 'bugsink');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            BugsinkReadCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/bugsink.php' => config_path('bugsink.php'),
        ], 'bugsink-config');
    }
}
