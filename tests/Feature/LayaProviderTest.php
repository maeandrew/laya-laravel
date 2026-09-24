<?php

namespace Laya\Laravel\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Classification\Score;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Ai\Responses\Data\ScoreAnswer;
use Laya\Laravel\LayaProvider;
use Laya\Laravel\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class LayaProviderTest extends TestCase
{
    public function test_it_registers_a_keyless_classification_provider(): void
    {
        $this->assertSame('http://localhost:8000/v1', config('laya.url'));
        $this->assertSame('laya', config('ai.providers.laya.driver'));

        $provider = app(AiManager::class)->classificationProvider('laya');

        $this->assertInstanceOf(LayaProvider::class, $provider);
        $this->assertSame('auto', $provider->defaultClassificationModel());
        $this->assertNull($provider->providerCredentials()['key']);
    }

    public function test_it_sends_the_laya_wire_format_and_maps_all_answer_types(): void
    {
        config([
            'ai.providers.laya.url' => 'http://laya.test/v1/',
            'ai.providers.laya.key' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(<<<'JSON'
                {
                    "model": "laya-rl-agent",
                    "answers": {
                        "urgent": {"type": "noul", "noul": 0.8},
                        "department": {
                            "type": "choice",
                            "choice": "billing",
                            "probabilities": {"billing": 0.9, "technical": 0.1},
                            "confidence": 0.9
                        },
                        "frustration": {
                            "type": "score",
                            "score": 1.25,
                            "legend": {"0": "Calm", "1": "Frustrated", "2": "Angry"},
                            "probabilities": {"0": 0.1, "1": 0.55, "2": 0.35},
                            "confidence": 0.55
                        }
                    },
                    "usage": {"input_tokens": 42, "output_tokens": 0}
                }
                JSON, 200, ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = Classification::of(['message' => 'Please refund my invoice.'])
            ->questions([
                'urgent' => new Boolean('Does this need an immediate response?', [
                    'true' => 'Time-sensitive',
                    'false' => 'No deadline',
                ]),
                'department' => new Choice('Which team should handle this?', [
                    'billing' => 'Payments and refunds',
                    'technical' => 'Bugs and outages',
                ]),
                'frustration' => new Score('How frustrated is the customer?', [
                    'Calm',
                    'Frustrated',
                    'Angry',
                ]),
            ])
            ->classify('laya');

        $this->assertInstanceOf(BooleanAnswer::class, $result['urgent']);
        $this->assertSame(0.8, $result['urgent']->probability);
        $this->assertInstanceOf(ChoiceAnswer::class, $result['department']);
        $this->assertSame('billing', $result['department']->choice);
        $this->assertSame(0.9, $result['department']->probabilityOf('billing'));
        $this->assertInstanceOf(ScoreAnswer::class, $result['frustration']);
        $this->assertSame([0, 1, 2], array_keys($result['frustration']->legend));
        $this->assertSame('Frustrated', $result['frustration']->label());
        $this->assertSame(42, $result->usage->inputTokens);
        $this->assertSame('laya-rl-agent', $result->meta->model);
        $this->assertSame('laya', $result->meta->provider);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'http://laya.test/v1/systemone'
                && ! $request->hasHeader('Authorization')
                && $body['model'] === 'auto'
                && $body['state'] === ['message' => 'Please refund my invoice.']
                && $body['questions'] === [
                    'urgent' => [
                        'type' => 'noul',
                        'instructions' => 'Does this need an immediate response?',
                        'criteria' => ['true' => 'Time-sensitive', 'false' => 'No deadline'],
                    ],
                    'department' => [
                        'type' => 'choice',
                        'instructions' => 'Which team should handle this?',
                        'criteria' => ['billing' => 'Payments and refunds', 'technical' => 'Bugs and outages'],
                    ],
                    'frustration' => [
                        'type' => 'score',
                        'instructions' => 'How frustrated is the customer?',
                        'criteria' => ['Calm', 'Frustrated', 'Angry'],
                    ],
                ];
        });
    }

    public function test_it_sends_a_bearer_token_and_custom_headers_when_configured(): void
    {
        config([
            'ai.providers.laya.url' => 'https://laya.example/v1',
            'ai.providers.laya.key' => 'test-secret',
            'ai.providers.laya.headers' => ['X-Client' => 'laya-laravel-tests'],
            'ai.providers.laya.models.classification.default' => 'multilingual',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'model' => 'laya-rl-agent',
                'answers' => ['urgent' => ['type' => 'noul', 'noul' => 0.75]],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 0],
            ]),
        ]);

        Classification::of('Please respond today.')
            ->question('urgent', new Boolean('Is this time-sensitive?'))
            ->classify('laya');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://laya.example/v1/systemone'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request->hasHeader('X-Client', 'laya-laravel-tests')
            && $request->data()['model'] === 'multilingual');
    }

    #[DefineEnvironment('defineUserProviderEntry')]
    public function test_a_user_entry_in_ai_config_is_merged_over_the_package_config(): void
    {
        $this->assertSame([
            'driver' => 'laya',
            'url' => 'http://gpu-box:8000/v1',
            'key' => 'user-secret',
            'models' => ['classification' => ['default' => 'auto']],
        ], config('ai.providers.laya'));
    }

    protected function defineUserProviderEntry($app): void
    {
        // Mirrors an entry written like laravel/ai's own, with LAYA_URL set but
        // LAYA_MODEL left unset or empty in .env.
        $app['config']->set('laya.url', 'http://gpu-box:8000/v1');
        $app['config']->set('ai.providers.laya', [
            'driver' => 'laya',
            'key' => 'user-secret',
            'url' => null,
            'models' => ['classification' => ['default' => '']],
        ]);
    }

    public function test_a_blank_model_falls_back_to_auto(): void
    {
        config(['ai.providers.laya.models.classification.default' => '']);

        $this->assertSame('auto', app(AiManager::class)->classificationProvider('laya')->defaultClassificationModel());
    }

    public function test_a_blank_url_fails_with_a_clear_message(): void
    {
        config(['ai.providers.laya.url' => '']);

        Http::preventStrayRequests();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LAYA_URL');

        Classification::of('Please respond today.')
            ->question('urgent', new Boolean('Is this time-sensitive?'))
            ->classify('laya');
    }

    public function test_the_driver_survives_a_rebuilt_ai_manager(): void
    {
        app()->forgetInstance(AiManager::class);

        $this->assertInstanceOf(LayaProvider::class, app(AiManager::class)->classificationProvider('laya'));
    }

    public function test_laravel_classification_fake_works_without_http_requests(): void
    {
        Classification::fake();

        $result = Classification::of('A test ticket.')
            ->question('urgent', new Boolean('Is this urgent?'))
            ->classify('laya');

        $this->assertInstanceOf(BooleanAnswer::class, $result['urgent']);
        Classification::assertClassified(fn ($prompt): bool => $prompt->provider->name() === 'laya');
        Http::assertNothingSent();
    }
}
