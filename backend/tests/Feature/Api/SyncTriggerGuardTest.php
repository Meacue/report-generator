<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncJob;
use App\Jobs\RunSyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncTriggerGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_trigger_returns_422_when_no_settings_row(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/sync/trigger');

        $response->assertStatus(422)
            ->assertJson([
                'error'   => 'Credentials are not configured',
                'missing' => ['gitlab_token', 'bitrix24_rest_url', 'bitrix24_user_id', 'bitrix24_api_key'],
            ]);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('sync_jobs', 0);
    }

    public function test_trigger_returns_422_when_only_gitlab_token_set(): void
    {
        Queue::fake();

        Setting::factory()->create([
            'gitlab_token'      => 'test-gitlab-token',
            'bitrix24_rest_url' => null,
            'bitrix24_user_id'  => null,
            'bitrix24_api_key'  => null,
        ]);

        $response = $this->postJson('/api/sync/trigger');

        $response->assertStatus(422)
            ->assertJson([
                'error'   => 'Credentials are not configured',
                'missing' => ['bitrix24_rest_url', 'bitrix24_user_id', 'bitrix24_api_key'],
            ]);

        Queue::assertNothingPushed();
    }

    public function test_trigger_returns_422_when_bitrix_webhook_partial(): void
    {
        Queue::fake();

        Setting::factory()->create([
            'gitlab_token'      => 'test-gitlab-token',
            'bitrix24_rest_url' => 'https://example-portal.bitrix24.ru/rest',
            'bitrix24_user_id'  => '1',
            'bitrix24_api_key'  => null,
        ]);

        $response = $this->postJson('/api/sync/trigger');

        $response->assertStatus(422)
            ->assertJson([
                'error'   => 'Credentials are not configured',
                'missing' => ['bitrix24_api_key'],
            ]);

        Queue::assertNothingPushed();
    }

    public function test_trigger_returns_202_and_dispatches_to_sync_queue_when_credentials_present(): void
    {
        Queue::fake();

        Setting::factory()->create([
            'gitlab_token'      => 'test-gitlab-token',
            'bitrix24_rest_url' => 'https://example-portal.bitrix24.ru/rest',
            'bitrix24_user_id'  => '1',
            'bitrix24_api_key'  => 'testwebhookkey00',
        ]);

        $response = $this->postJson('/api/sync/trigger');

        $response->assertStatus(202);
        Queue::assertPushedOn('sync', RunSyncJob::class);
        $this->assertDatabaseHas('sync_jobs', ['status' => SyncStatus::InProgress->value]);
    }

    public function test_resync_returns_422_when_credentials_missing(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/sync/resync', [
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Credentials are not configured',
            ]);

        Queue::assertNothingPushed();
    }

    public function test_resync_returns_202_with_valid_credentials_and_dates(): void
    {
        Queue::fake();

        Setting::factory()->create([
            'gitlab_token'      => 'test-gitlab-token',
            'bitrix24_rest_url' => 'https://example-portal.bitrix24.ru/rest',
            'bitrix24_user_id'  => '1',
            'bitrix24_api_key'  => 'testwebhookkey00',
        ]);

        $response = $this->postJson('/api/sync/resync', [
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $response->assertStatus(202);

        Queue::assertPushedOn('sync', RunSyncJob::class);

        /** @var SyncJob $syncJob */
        $syncJob = SyncJob::query()->latest('started_at')->first();
        $this->assertNotNull($syncJob);
        $this->assertSame('2024-01-01', $syncJob->params['date_from'] ?? null);
        $this->assertSame('2024-01-31', $syncJob->params['date_to'] ?? null);
    }

    public function test_trigger_returns_409_when_running_takes_precedence_over_422(): void
    {
        Queue::fake();

        SyncJob::create([
            'type'       => 'full',
            'status'     => SyncStatus::InProgress,
            'started_at' => now(),
        ]);

        // No Setting record — credentials are missing, but 409 must take priority over 422
        $response = $this->postJson('/api/sync/trigger');

        $response->assertStatus(409)
            ->assertJson(['error' => 'Sync is already in progress']);
    }
}
