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
}
