<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_default_settings(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJson([
                'gitlab_username'      => null,
                'bitrix24_user_id'     => null,
                'llm_provider'         => 'claude',
                'developer_name'       => null,
                'developer_position'   => null,
                'sync_schedule_time'   => '03:00',
                'has_gitlab_token'     => false,
                'has_bitrix24_api_key' => false,
                'has_llm_api_key'      => false,
            ]);
    }

    public function test_update_settings(): void
    {
        $response = $this->putJson('/api/settings', [
            'gitlab_username'    => 'john.doe',
            'developer_name'     => 'John Doe',
            'developer_position' => 'Senior Developer',
            'llm_provider'       => 'openai',
            'sync_schedule_time' => '09:00',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Settings updated']);

        $this->assertDatabaseHas('settings', [
            'gitlab_username'    => 'john.doe',
            'developer_name'     => 'John Doe',
            'developer_position' => 'Senior Developer',
            'sync_schedule_time' => '09:00',
        ]);
    }

    public function test_get_settings_hides_tokens(): void
    {
        Setting::factory()->create([
            'gitlab_token'     => 'secret-token-123',
            'bitrix24_api_key' => 'secret-key-456',
            'llm_api_key'      => 'sk-secret-789',
            'gitlab_username'  => 'testuser',
        ]);

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonMissing(['gitlab_token' => 'secret-token-123'])
            ->assertJsonMissing(['bitrix24_api_key' => 'secret-key-456'])
            ->assertJsonMissing(['llm_api_key' => 'sk-secret-789'])
            ->assertJson([
                'has_gitlab_token'     => true,
                'has_bitrix24_api_key' => true,
                'has_llm_api_key'      => true,
                'gitlab_username'      => 'testuser',
            ]);

        $content = $response->json();
        $this->assertArrayNotHasKey('gitlab_token', $content);
        $this->assertArrayNotHasKey('bitrix24_api_key', $content);
        $this->assertArrayNotHasKey('llm_api_key', $content);
    }

    public function test_get_settings_survives_corrupted_encrypted_columns(): void
    {
        // Insert raw garbage that looks like a base64 envelope but will fail MAC validation.
        // The controller reads raw values via getRawOriginal(), so it must not attempt decryption.
        DB::table('settings')->insert([
            'gitlab_token'            => 'eyJpdiI6ImNvcnJ1cHRlZCIsInZhbHVlIjoiYmFkIn0=',
            'gitlab_username'         => null,
            'gitlab_email'            => null,
            'bitrix24_api_key'        => 'eyJpdiI6ImNvcnJ1cHRlZCIsInZhbHVlIjoiYmFkIn0=',
            'bitrix24_user_id'        => null,
            'llm_provider'            => 'claude',
            'llm_api_key'             => 'eyJpdiI6ImNvcnJ1cHRlZCIsInZhbHVlIjoiYmFkIn0=',
            'llm_system_prompt'       => null,
            'enriched_prompt_enabled' => true,
            'developer_name'          => null,
            'developer_position'      => null,
            'sync_schedule_time'      => '03:00',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJson([
                'has_gitlab_token'     => true,
                'has_bitrix24_api_key' => true,
                'has_llm_api_key'      => true,
            ]);
    }

    public function test_update_settings_parses_webhook_url_and_stores_three_parts(): void
    {
        // GIVEN: a valid Bitrix24 webhook URL
        $webhookUrl = 'https://aerokod.bitrix24.ru/rest/250/hcdim7lf2ogndgu3/';

        // WHEN: PUT /api/settings with the webhook URL
        $response = $this->putJson('/api/settings', [
            'bitrix24_webhook_url' => $webhookUrl,
        ]);

        // THEN: response is 200 and all three credential columns are persisted
        $response->assertOk()
            ->assertJson(['message' => 'Settings updated']);

        $this->assertDatabaseHas('settings', [
            'bitrix24_user_id'  => '250',
            'bitrix24_rest_url' => 'https://aerokod.bitrix24.ru/rest',
        ]);

        /** @var Setting $setting */
        $setting = Setting::query()->first();
        $this->assertSame('hcdim7lf2ogndgu3', $setting->bitrix24_api_key);
    }

    public function test_update_settings_rejects_invalid_webhook_url(): void
    {
        // GIVEN: a garbage webhook URL that fails regex validation
        // WHEN: PUT /api/settings with invalid webhook URL
        $response = $this->putJson('/api/settings', [
            'bitrix24_webhook_url' => 'garbage',
        ]);

        // THEN: 422 Unprocessable Entity with error for the webhook field
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bitrix24_webhook_url']);
    }

    public function test_update_settings_without_webhook_keeps_existing_credentials(): void
    {
        // GIVEN: a Setting already configured with Bitrix24 webhook credentials
        Setting::factory()->create([
            'bitrix24_rest_url' => 'https://aerokod.bitrix24.ru/rest',
            'bitrix24_user_id'  => '250',
            'bitrix24_api_key'  => 'hcdim7lf2ogndgu3',
            'gitlab_username'   => 'old.user',
        ]);

        // WHEN: PUT /api/settings without bitrix24_webhook_url (only updating gitlab_username)
        $response = $this->putJson('/api/settings', [
            'gitlab_username' => 'new.user',
        ]);

        // THEN: response is 200 and webhook credentials are untouched
        $response->assertOk();

        $this->assertDatabaseHas('settings', [
            'bitrix24_rest_url' => 'https://aerokod.bitrix24.ru/rest',
            'bitrix24_user_id'  => '250',
            'gitlab_username'   => 'new.user',
        ]);

        /** @var Setting $setting */
        $setting = Setting::query()->first();
        $this->assertSame('hcdim7lf2ogndgu3', $setting->bitrix24_api_key);
    }

    public function test_settings_index_returns_webhook_configured_true_when_all_parts_present(): void
    {
        // GIVEN: a Setting with all three webhook parts filled in
        Setting::factory()->create([
            'bitrix24_rest_url' => 'https://aerokod.bitrix24.ru/rest',
            'bitrix24_user_id'  => '250',
            'bitrix24_api_key'  => 'hcdim7lf2ogndgu3',
        ]);

        // WHEN: GET /api/settings
        $response = $this->getJson('/api/settings');

        // THEN: bitrix24_webhook_configured is true
        $response->assertOk()
            ->assertJson(['bitrix24_webhook_configured' => true]);
    }

    public function test_settings_index_returns_webhook_configured_false_when_no_setting_exists(): void
    {
        // GIVEN: no Setting record in the database
        // WHEN: GET /api/settings
        $response = $this->getJson('/api/settings');

        // THEN: bitrix24_webhook_configured is false
        $response->assertOk()
            ->assertJson(['bitrix24_webhook_configured' => false]);
    }

    public function test_settings_index_returns_webhook_configured_false_when_rest_url_missing(): void
    {
        // GIVEN: a Setting with user_id and api_key but no rest_url
        Setting::factory()->create([
            'bitrix24_rest_url' => null,
            'bitrix24_user_id'  => '250',
            'bitrix24_api_key'  => 'hcdim7lf2ogndgu3',
        ]);

        // WHEN: GET /api/settings
        $response = $this->getJson('/api/settings');

        // THEN: bitrix24_webhook_configured is false (incomplete config)
        $response->assertOk()
            ->assertJson(['bitrix24_webhook_configured' => false]);
    }
}
