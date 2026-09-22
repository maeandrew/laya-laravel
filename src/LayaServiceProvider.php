<?php

namespace Laya\Laravel;

use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

class LayaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laya.php', 'laya');
    }

    public function boot(): void
    {
        $config = $this->app['config'];

        // Registering the provider under ai.providers keeps `classify('laya')`
        // working without the user having to hand-edit laravel/ai's own config.
        if (! $config->has('ai.providers.laya')) {
            $config->set('ai.providers.laya', $config->get('laya'));
        }

        $this->app->make(AiManager::class)->extend(
            'laya',
            fn ($app, array $configuration) => new LayaProvider($configuration, $app['events']),
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laya.php' => $this->app->configPath('laya.php'),
            ], ['laya', 'laya-config']);
        }
    }
}
