<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domain\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SettingsResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_flag_clears_encrypted_fields(): void
    {
        Setting::factory()->create([
            'gitlab_token'     => 'glpat-real-token-abc123',
            'bitrix24_api_key' => 'bitrix-key-xyz789',
            'llm_api_key'      => 'sk-ant-api03-fakekey',
        ]);

        $command = $this->artisan('settings:reset', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Cleared encrypted credentials in 1 settings row(s).');
        $command->run();

        $raw = DB::table('settings')->first();
        $this->assertNotNull($raw);
        $this->assertNull($raw->gitlab_token);
        $this->assertNull($raw->bitrix24_api_key);
        $this->assertNull($raw->llm_api_key);
    }

    public function test_confirmation_aborts_without_force(): void
    {
        Setting::factory()->create([
            'gitlab_token' => 'glpat-real-token-abc123',
        ]);

        $rawBefore = DB::table('settings')->value('gitlab_token');

        $command = $this->artisan('settings:reset');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsConfirmation('Reset encrypted credentials (gitlab_token, bitrix24_api_key, llm_api_key)?', 'no');
        $command->expectsOutputToContain('Aborted.');
        $command->run();

        $rawAfter = DB::table('settings')->value('gitlab_token');
        $this->assertSame($rawBefore, $rawAfter);
        $this->assertNotNull($rawAfter);
    }

    public function test_confirmation_yes_clears_fields(): void
    {
        Setting::factory()->create([
            'gitlab_token'     => 'glpat-real-token-abc123',
            'bitrix24_api_key' => 'bitrix-key-xyz789',
            'llm_api_key'      => 'sk-ant-api03-fakekey',
        ]);

        $command = $this->artisan('settings:reset');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsConfirmation('Reset encrypted credentials (gitlab_token, bitrix24_api_key, llm_api_key)?', 'yes');
        $command->expectsOutputToContain('Cleared encrypted credentials in 1 settings row(s).');
        $command->run();

        $raw = DB::table('settings')->first();
        $this->assertNotNull($raw);
        $this->assertNull($raw->gitlab_token);
        $this->assertNull($raw->bitrix24_api_key);
        $this->assertNull($raw->llm_api_key);
    }

    public function test_runs_on_empty_settings_table(): void
    {
        $command = $this->artisan('settings:reset', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('0 settings row(s)');
        $command->assertExitCode(0);
    }

    public function test_leaves_non_encrypted_fields_intact(): void
    {
        Setting::factory()->create([
            'gitlab_username'  => 'john',
            'developer_name'   => 'John',
            'bitrix24_user_id' => '42',
            'gitlab_token'     => 'glpat-real-token-abc123',
        ]);

        $command = $this->artisan('settings:reset', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->run();

        $raw = DB::table('settings')->first();
        $this->assertNotNull($raw);
        $this->assertSame('john', $raw->gitlab_username);
        $this->assertSame('John', $raw->developer_name);
        $this->assertSame('42', $raw->bitrix24_user_id);
        $this->assertNull($raw->gitlab_token);
        $this->assertNull($raw->bitrix24_api_key);
        $this->assertNull($raw->llm_api_key);
    }
}
