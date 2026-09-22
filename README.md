# Laya Laravel

An unofficial Laravel AI provider for [Laya](https://github.com/NandhaKishorM/laya). It lets [`laravel/ai`](https://github.com/laravel/ai) send typed classification questions to a self-hosted `laya-serve` server.

> This is a community package. It is not affiliated with or endorsed by Laravel, Convai Innovations/Laya, or TypeSafe.

The provider supports Laya's `choice`, `score`, and `noul` answers and uses Laya's automatic script and language routing by default.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- `laravel/ai` 1.x with its Classification API (currently a development release)
- A reachable Laya server exposing `POST /v1/systemone`

The Classification API is not available in the latest tagged `laravel/ai` release yet, so Composer must be able to resolve its `1.x-dev` branch. This package declares that development dependency directly.

## Install

Until the first Packagist release, add the GitHub repository to your application's `composer.json` and require its `main` branch:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/maeandrew/laya-laravel"
        }
    ]
}
```

```bash
composer require maeandrew/laya-laravel:dev-main
```

Laravel discovers the service provider automatically. To publish its configuration file, run:

```bash
php artisan vendor:publish --tag=laya-config
```

## Configure

Set the connection in `.env`:

```dotenv
LAYA_URL=http://localhost:8000/v1
# Set this only when the server uses LAYA_API_KEY:
LAYA_API_KEY=
# Optional checkpoint override; defaults to automatic routing:
LAYA_MODEL=auto
```

The provider is registered as `laya` in `laravel/ai`. Pass it to `classify()` explicitly, or set `ai.default_for_classification` to `laya` in your application's `config/ai.php`.

## Use

```php
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Classification\Score;

$result = Classification::of([
    'subject' => 'Duplicate charge',
    'body' => 'I was charged twice. Please refund the duplicate today.',
])->questions([
    'urgent' => new Boolean('Does this request need an immediate response?', [
        'true' => 'Explicitly time-sensitive',
        'false' => 'No deadline is expressed',
    ]),
    'department' => new Choice('Which team should handle this request?', [
        'billing' => 'Payments, invoices, and refunds',
        'technical' => 'Bugs and outages',
        'sales' => 'Pricing and upgrades',
    ]),
    'frustration' => new Score('How frustrated is the customer?', [
        'Calm',
        'Frustrated',
        'Very angry',
    ]),
])->classify('laya');

$result['department']->choice;        // "billing"
$result['department']->confidence;    // calibrated confidence
$result['urgent']->isTrue(0.8);       // apply your own action threshold
$result['frustration']->label();      // most probable level label
$result->meta->model;                 // "laya-rl-agent"
```

Use the same fake API provided by `laravel/ai` in application tests:

```php
Classification::fake();

$result = Classification::of('A test ticket.')
    ->question('urgent', new Boolean('Is this urgent?'))
    ->classify('laya');

Classification::assertClassified(fn ($prompt) => $prompt->provider === 'laya');
```

## Laya server

The package is a client; it does not install or start the Python model server. Install and run Laya with its optional HTTP dependencies on a machine reachable by Laravel:

```bash
pip install 'laya[serve]'
LAYA_DEVICE=cpu LAYA_PRELOAD=1 LAYA_MODELS=english,multilingual laya-serve
```

The default URL is `http://localhost:8000/v1`. Set `LAYA_URL` to the corresponding base URL in your Laravel environment. If the server uses `LAYA_API_KEY`, set the same value in Laravel; the package adds the matching bearer token. With no key configured, it omits the Authorization header.

Keep `LAYA_PRELOAD=1` for multilingual routing in a production service. The Router otherwise keeps one checkpoint loaded and may need to cold-load another after a language switch, which can exceed the Laravel AI classification request's default 30-second timeout. The multilingual checkpoint's accuracy is weaker than the English checkpoint on some tasks; evaluate it on your own data before relying on automated decisions.

By default, `LAYA_MODEL=auto` lets Laya route each request based on its state. Set it to `english`, `multilingual`, or `typed-decisions` only when you need to pin a checkpoint.

## Development

```bash
composer update
composer test
composer test:lint
```

The Testbench suite fakes HTTP and makes no model downloads or external API calls.

## License

MIT. See [LICENSE](LICENSE).
