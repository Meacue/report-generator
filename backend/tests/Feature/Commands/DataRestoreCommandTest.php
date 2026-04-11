<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class DataRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_recovers_soft_deleted_records(): void
    {
        $branch = Branch::factory()->create();
        $commit = Commit::factory()->create(['branch_id' => $branch->id]);
        $task = Task::factory()->create();
        $matchResult = MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);

        $deletedAt = now();

        $branch->delete();
        $commit->delete();
        $task->delete();
        $matchResult->delete();

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertSoftDeleted('commits', ['id' => $commit->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSoftDeleted('match_results', ['id' => $matchResult->id]);

        $from = $deletedAt->copy()->subMinute()->format('Y-m-d');
        $to = $deletedAt->copy()->addDay()->format('Y-m-d');

        $command = $this->artisan('data:restore', [
            '--from' => $from,
            '--to'   => $to,
        ]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->run();

        $this->assertNotSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertNotSoftDeleted('commits', ['id' => $commit->id]);
        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertNotSoftDeleted('match_results', ['id' => $matchResult->id]);
    }

    public function test_restore_dry_run_does_not_modify_records(): void
    {
        $branch = Branch::factory()->create();
        $task = Task::factory()->create();

        $deletedAt = now();

        $branch->delete();
        $task->delete();

        $from = $deletedAt->copy()->subMinute()->format('Y-m-d');
        $to = $deletedAt->copy()->addDay()->format('Y-m-d');

        $command = $this->artisan('data:restore', [
            '--from'    => $from,
            '--to'      => $to,
            '--dry-run' => true,
        ]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->run();

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_restore_requires_date_options(): void
    {
        $command = $this->artisan('data:restore');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(1);
    }

    public function test_restore_requires_from_option(): void
    {
        $command = $this->artisan('data:restore', ['--to' => '2026-03-13']);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(1);
    }

    public function test_restore_requires_to_option(): void
    {
        $command = $this->artisan('data:restore', ['--from' => '2026-03-01']);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(1);
    }

    public function test_restore_rejects_invalid_date_format(): void
    {
        $command = $this->artisan('data:restore', [
            '--from' => 'not-a-date',
            '--to'   => '2026-03-13',
        ]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(1);
    }

    public function test_restore_outputs_record_counts(): void
    {
        $branch = Branch::factory()->create();
        $deletedAt = now();
        $branch->delete();

        $from = $deletedAt->copy()->subMinute()->format('Y-m-d');
        $to = $deletedAt->copy()->addDay()->format('Y-m-d');

        $command = $this->artisan('data:restore', [
            '--from' => $from,
            '--to'   => $to,
        ]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Branches');
    }

    public function test_restore_outputs_dry_run_notice(): void
    {
        $command = $this->artisan('data:restore', [
            '--from'    => '2026-03-01',
            '--to'      => '2026-03-31',
            '--dry-run' => true,
        ]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('DRY RUN');
    }
}
