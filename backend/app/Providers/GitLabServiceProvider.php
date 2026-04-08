<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use App\Services\GitLab\GitLabClient;
use App\Services\GitLab\GitLabClientInterface;
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
