<?php

namespace Laya\Laravel;

use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Providers\ClassificationProvider;
use Laravel\Ai\Gateway\TypeSafeGateway;

/**
 * laya-serve speaks the same /systemone wire format as TypeSafe, so only the
 * connection differs: a self-hosted URL and an optional bearer token.
 */
class LayaGateway extends TypeSafeGateway
{
    /**
     * Get an HTTP client for a laya-serve instance.
     */
    protected function client(ClassificationProvider $provider, int $timeout = 30): PendingRequest
    {
        $configuration = $provider->additionalConfiguration();
        $key = $provider->providerCredentials()['key'] ?? null;

        if (blank($url = $configuration['url'] ?? null)) {
            throw new InvalidArgumentException('The Laya server URL is not configured. Set LAYA_URL to your laya-serve instance.');
        }

        return $this->createClient(
            rtrim($url, '/'),
            array_filter([
                // laya-serve only checks the bearer token when it was started
                // with LAYA_API_KEY, which self-hosted instances often skip.
                'Authorization' => filled($key) ? 'Bearer '.$key : null,
                'Content-Type' => 'application/json',
            ]),
            $configuration['headers'] ?? [],
            $timeout,
        );
    }
}
