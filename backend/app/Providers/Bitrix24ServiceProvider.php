<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Models\Setting;
use App\Infrastructure\Bitrix24\Bitrix24Client;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class Bitrix24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Bitrix24ClientInterface::class, function (): Bitrix24ClientInterface {
            $setting = Setting::query()->first();

            /** @var string $url */
            $url = config('services.bitrix24.url', '');

            /** @var string $userId */
            $userId = config('services.bitrix24.user_id', '');
            if ($setting !== null && $setting->bitrix24_user_id !== null) {
                $userId = (string) $setting->bitrix24_user_id;
            }

            /** @var string $apiKey */
            $apiKey = config('services.bitrix24.api_key', '');

            try {
                if ($setting !== null && $setting->bitrix24_api_key !== null) {
                    $apiKey = $setting->bitrix24_api_key;
                }
                // @phpstan-ignore catch.neverThrown (encrypted cast throws at runtime; static analysis can't see it)
            } catch (DecryptException $e) {
                Log::warning(
                    'Bitrix24 API key decryption failed, falling back to config. Run "php artisan settings:reset" and re-enter credentials in Settings.',
                    ['error' => $e->getMessage()],
                );
            }

            return new Bitrix24Client(
                url: $url,
                userId: $userId,
                apiKey: $apiKey,
            );
        });
    }
}
