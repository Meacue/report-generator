<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Models\Setting;
use App\Infrastructure\Bitrix24\Bitrix24Client;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
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
            $userId = $setting->bitrix24_user_id ?? config('services.bitrix24.user_id', '');
            /** @var string $apiKey */
            $apiKey = $setting->bitrix24_api_key ?? config('services.bitrix24.api_key', '');

            return new Bitrix24Client(
                url: $url,
                userId: $userId,
                apiKey: $apiKey,
            );
        });
    }
}
