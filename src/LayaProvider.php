<?php

namespace Laya\Laravel;

use Laravel\Ai\Contracts\Gateway\ClassificationGateway;
use Laravel\Ai\Providers\TypeSafeProvider;

class LayaProvider extends TypeSafeProvider
{
    /**
     * Get the name of the default classification model.
     *
     * "auto" is not a checkpoint: it is the absence of one, which is how the
     * Router is told to pick from the state's script and language instead.
     */
    public function defaultClassificationModel(): string
    {
        return ($this->config['models']['classification']['default'] ?? null) ?: 'auto';
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
