<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Models\Setting;
use App\Infrastructure\GitLab\GitLabClient;
use App\Domain\GitLab\Services\GitLabClientInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class GitLabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitLabClientInterface::class, function (): GitLabClientInterface {
            $setting = Setting::query()->first();

            /** @var string $token */
            $token = config('services.gitlab.token', '');

            try {
                if ($setting !== null && $setting->gitlab_token !== null) {
                    $token = $setting->gitlab_token;
                }
                // @phpstan-ignore catch.neverThrown (encrypted cast throws at runtime; static analysis can't see it)
            } catch (DecryptException $e) {
                Log::warning(
                    'GitLab token decryption failed, falling back to config. Run "php artisan settings:reset" and re-enter credentials in Settings.',
                    ['error' => $e->getMessage()],
                );
            }

            /** @var string $url */
            $url = config('services.gitlab.url', 'https://gitlab.com');

            return new GitLabClient($url, $token);
        });
    }
}
