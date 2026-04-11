<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\ProjectMapping;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Actions\SyncBitrix24;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncBitrix24Test extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private SyncBitrix24 $action;

    public function test_creates_task_records(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                [
                    'id'             => '1001',
                    'title'          => 'Fix login page',
                    'status'         => '5',
                    'statusComplete' => '5',
                    'groupId'        => '5',
                    'group'          => ['id' => '5', 'name' => 'Project Alpha'],
                    'closedDate'     => '2026-03-10T15:00:00+03:00',
                    'url'            => 'https://bitrix24.example.com/task/1001',
                ],
                [
                    'id'             => '1002',
                    'title'          => 'Add dashboard',
                    'status'         => '3',
                    'statusComplete' => '0',
                    'groupId'        => '5',
                    'group'          => ['id' => '5', 'name' => 'Project Alpha'],
                    'closedDate'     => null,
                    'url'            => 'https://bitrix24.example.com/task/1002',
                ],
            ]);

        $log = ($this->action)();

        $this->assertNull($log->error_message, 'Sync error: ' . (string) $log->error_message);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::Bitrix24, $log->source);
        $this->assertSame(2, $log->items_synced);
        $this->assertDatabaseCount('tasks', 2);

        /** @var Task $completedTask */
        $completedTask = Task::query()->where('bitrix24_task_id', 1001)->first();
        $this->assertSame(TaskStatus::Completed, $completedTask->status);

        /** @var Task $inProgressTask */
        $inProgressTask = Task::query()->where('bitrix24_task_id', 1002)->first();
        $this->assertSame(TaskStatus::InProgress, $inProgressTask->status);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->action = new SyncBitrix24(
            bitrix24Client: $this->bitrix24Client,
        );
    }
}
