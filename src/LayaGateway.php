<?php

namespace Laya\Laravel;

use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Contracts\Gateway\ClassificationGateway;
use Laravel\Ai\Contracts\Providers\ClassificationProvider;
use Laravel\Ai\Gateway\Concerns\AnswersQuestions;
use Laravel\Ai\Gateway\Concerns\CreatesClient;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;

class LayaGateway implements ClassificationGateway
{
    use AnswersQuestions;
    use CreatesClient;
    use HandlesFailoverErrors;

    /**
     * The checkpoints laya-serve resolves by name. Any other value -- including
     * the default "auto" -- fails its lookup on purpose, which is how the Router
     * is told to choose from the state's own script and language.
     */
    public const CHECKPOINTS = ['english', 'multilingual', 'typed-decisions'];

    /**
     * Get the path of the endpoint that answers questions.
     */
    protected function classificationEndpoint(): string
    {
        return '/systemone';
    }

    /**
     * Get an HTTP client for a laya-serve instance.
     */
    protected function client(ClassificationProvider $provider, int $timeout = 30): PendingRequest
    {
        $configuration = $provider->additionalConfiguration();
        $key = $provider->providerCredentials()['key'] ?? null;

        return $this->createClient(
            rtrim($configuration['url'] ?? 'http://localhost:8000/v1', '/'),
            array_filter([
                // laya-serve only checks the bearer token when it was started
                // with LAYA_API_KEY, which self-hosted instances often skip.
                'Authorization' => $key ? 'Bearer '.$key : null,
                'Content-Type' => 'application/json',
            ]),
            $configuration['headers'] ?? [],
            $timeout,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function overloadedStatusCodes(): array
    {
        return [529, 502, 503, 504, 520, 522, 524];
    }
}
