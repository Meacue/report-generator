<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domain\GitLab\Services\GitLabClientInterface;
use App\Domain\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GitLabServiceProviderDecryptTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrupted_gitlab_token_falls_back_to_config_without_throwing(): void
    {
        // Insert a raw value that looks like a base64 envelope but has invalid MAC/structure.
        // The encrypted cast will try to decrypt it and throw DecryptException.
        DB::table('settings')->insert([
            'gitlab_token'            => 'eyJpdiI6ImNvcnJ1cHRlZCIsInZhbHVlIjoiYmFkIn0=',
            'gitlab_username'         => null,
            'gitlab_email'            => null,
            'bitrix24_api_key'        => null,
            'bitrix24_user_id'        => null,
            'llm_provider'            => 'claude',
            'llm_api_key'             => null,
            'llm_system_prompt'       => null,
            'enriched_prompt_enabled' => true,
            'developer_name'          => null,
            'developer_position'      => null,
            'sync_schedule_time'      => '03:00',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $warnings = [];
        Log::listen(function (object $event) use (&$warnings): void {
            /** @var object{level: string} $event */
            if (property_exists($event, 'level') && $event->level === 'warning') {
                $warnings[] = $event;
            }
        });

        $this->app->forgetInstance(GitLabClientInterface::class);

        $client = $this->app->make(GitLabClientInterface::class);

        $this->assertInstanceOf(GitLabClientInterface::class, $client);

        // The provider must have logged a warning about the decryption failure.
        $this->assertNotEmpty($warnings, 'Expected at least one warning log entry for decryption failure');
    }

    public function test_valid_gitlab_token_resolves_normally(): void
    {
        Setting::factory()->create(['gitlab_token' => 'valid-token-xyz']);

        $warnings = [];
        Log::listen(function (object $event) use (&$warnings): void {
            /** @var object{level: string} $event */
            if (property_exists($event, 'level') && $event->level === 'warning') {
                $warnings[] = $event;
            }
        });

        $this->app->forgetInstance(GitLabClientInterface::class);

        $client = $this->app->make(GitLabClientInterface::class);

        $this->assertInstanceOf(GitLabClientInterface::class, $client);

        // No warning must be logged when the token is intact.
        $this->assertEmpty($warnings, 'Expected no warning log entries for a valid token');
    }

    public function test_null_gitlab_token_uses_config_fallback(): void
    {
        // No Setting row at all — provider reads from config instead.
        $this->app->forgetInstance(GitLabClientInterface::class);

        $client = $this->app->make(GitLabClientInterface::class);

        $this->assertInstanceOf(GitLabClientInterface::class, $client);
    }
}
