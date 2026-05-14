<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SyncResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_flag_marks_in_progress_as_failed(): void
    {
        SyncJob::create(['type' => 'full', 'status' => SyncStatus::InProgress, 'started_at' => now()]);
        SyncJob::create(['type' => 'full', 'status' => SyncStatus::InProgress, 'started_at' => now()]);

        $command = $this->artisan('sync:reset', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Marked 2 SyncJob(s) as failed.');
        $command->assertExitCode(0);
        $command->run();

        $jobs = SyncJob::all();
        foreach ($jobs as $job) {
            $this->assertSame(SyncStatus::Failed, $job->status);
            $this->assertSame('Reset via sync:reset', $job->error_message);
            $this->assertNotNull($job->completed_at);
        }
    }

    public function test_does_not_touch_success_or_failed_jobs(): void
    {
        SyncJob::create([
            'type'          => 'full',
            'status'        => SyncStatus::Success,
            'error_message' => 'original-success',
            'started_at'    => now()->subHours(3),
            'completed_at'  => now()->subHours(2),
        ]);

        SyncJob::create([
            'type'          => 'full',
            'status'        => SyncStatus::Failed,
            'error_message' => 'original-failed',
            'started_at'    => now()->subHours(3),
            'completed_at'  => now()->subHours(2),
        ]);

        SyncJob::create([
            'type'          => 'full',
            'status'        => SyncStatus::InProgress,
            'error_message' => 'original-in-progress',
            'started_at'    => now()->subHour(),
            'completed_at'  => now()->subHour(),
        ]);

        $command = $this->artisan('sync:reset', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->run();

        /** @var SyncJob $successJob */
        $successJob = SyncJob::query()->where('status', SyncStatus::Success)->first();
        $this->assertSame('original-success', $successJob->error_message);

        /** @var SyncJob $failedJob */
        $failedJob = SyncJob::query()
            ->where('status', SyncStatus::Failed)
            ->where('error_message', 'original-failed')
            ->first();
        $this->assertNotNull($failedJob);

        /** @var SyncJob $resetJob */
        $resetJob = SyncJob::query()->where('error_message', 'Reset via sync:reset')->first();
        $this->assertNotNull($resetJob);
        $this->assertSame(SyncStatus::Failed, $resetJob->status);
    }

    public function test_confirmation_aborts_without_force(): void
    {
        SyncJob::create(['type' => 'full', 'status' => SyncStatus::InProgress, 'started_at' => now()]);

        $command = $this->artisan('sync:reset');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsConfirmation('Reset all in-progress SyncJob to failed?', 'no');
        $command->expectsOutput('Aborted.');
        $command->run();

        /** @var SyncJob $job */
        $job = SyncJob::query()->first();
        $this->assertSame(SyncStatus::InProgress, $job->status);
    }

    public function test_confirmation_yes_marks_failed(): void
    {
        SyncJob::create(['type' => 'full', 'status' => SyncStatus::InProgress, 'started_at' => now()]);

        $command = $this->artisan('sync:reset');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsConfirmation('Reset all in-progress SyncJob to failed?', 'yes');
        $command->expectsOutput('Marked 1 SyncJob(s) as failed.');
        $command->run();

        /** @var SyncJob $job */
        $job = SyncJob::query()->first();
        $this->assertSame(SyncStatus::Failed, $job->status);
        $this->assertSame('Reset via sync:reset', $job->error_message);
    }

    public function test_runs_on_empty_table(): void
    {
        $this->artisan('sync:reset', ['--force' => true])
            ->expectsOutput('Marked 0 SyncJob(s) as failed.')
            ->assertExitCode(0);
    }
}
