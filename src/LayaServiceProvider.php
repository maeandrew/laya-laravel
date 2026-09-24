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
        // An entry the user did add there still wins, but only for the values it
        // actually sets, so an unset env() in it falls back to config/laya.php.
        $config->set('ai.providers.laya', array_replace_recursive(
            $config->get('laya', []),
            $this->filledValues($config->get('ai.providers.laya', [])),
        ));

        // Deferred until the manager is resolved, so requests that never touch
        // laravel/ai don't build it, and a rebuilt manager gets the driver too.
        $this->callAfterResolving(AiManager::class, fn (AiManager $manager) => $manager->extend(
            'laya',
            fn ($app, array $configuration) => new LayaProvider($configuration, $app['events']),
        ));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laya.php' => $this->app->configPath('laya.php'),
            ], ['laya', 'laya-config']);
        }
    }

    /**
     * Drop null and empty-string values, recursively.
     */
    protected function filledValues(array $values): array
    {
        return array_filter(
            array_map(fn ($value) => is_array($value) ? $this->filledValues($value) : $value, $values),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
