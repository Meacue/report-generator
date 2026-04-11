<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Models\Setting;
use App\Domain\GitLab\Services\GitLabClient;
use App\Domain\GitLab\Services\GitLabClientInterface;
use Illuminate\Support\ServiceProvider;

final class GitLabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitLabClientInterface::class, function (): GitLabClientInterface {
            $setting = Setting::query()->first();

            /** @var string $token */
            $token = $setting->gitlab_token ?? config('services.gitlab.token', '');
            /** @var string $url */
            $url = config('services.gitlab.url', 'https://gitlab.com');

            return new GitLabClient($url, $token);
        });
    }
}
