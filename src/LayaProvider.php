<?php

namespace Laya\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\ClassificationGateway;
use Laravel\Ai\Contracts\Providers\ClassificationProvider;
use Laravel\Ai\Providers\Concerns\Classifies;
use Laravel\Ai\Providers\Concerns\HasClassificationGateway;
use Laravel\Ai\Providers\Provider;

class LayaProvider extends Provider implements ClassificationProvider
{
    use Classifies;
    use HasClassificationGateway;

    public function __construct(
        protected array $config,
        protected Dispatcher $events,
    ) {}

    /**
     * Get the name of the default classification model.
     *
     * "auto" is not a checkpoint: it is the absence of one, which is how the
     * Router is told to pick from the state's script and language instead.
     */
    public function defaultClassificationModel(): string
    {
        return $this->config['models']['classification']['default'] ?? 'auto';
    }

    /**
     * Get the credentials for the underlying AI provider.
     *
     * Overridden because a self-hosted Laya usually runs without a key at all,
     * where the parent's unconditional $config['key'] read warns.
     */
    public function providerCredentials(): array
    {
        return ['key' => $this->config['key'] ?? null];
    }

    /**
     * Get the provider's classification gateway.
     */
    public function classificationGateway(): ClassificationGateway
    {
        return $this->classificationGateway ??= new LayaGateway;
    }
}
