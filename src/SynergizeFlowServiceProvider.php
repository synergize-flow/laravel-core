<?php

namespace SynergizeFlow\Laravel;

use Illuminate\Support\ServiceProvider;

class SynergizeFlowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Allow developers to publish the config file
        $this->publishes([
            __DIR__.'/../config/synergizeflow.php' => config_path('synergizeflow.php'),
        ], 'synergizeflow-config');

        // Load the API routes for the package
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function register()
    {
        // Merge default config
        $this->mergeConfigFrom(
            __DIR__.'/../config/synergizeflow.php', 'synergizeflow'
        );
    }
}
