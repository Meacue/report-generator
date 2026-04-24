<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Actions\SyncBitrix24Tasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class SyncBitrix24TasksTest extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private SyncBitrix24Tasks $action;

    // ---------------------------------------------------------------------------
    // Happy path — user-wide fetch succeeds
    // ---------------------------------------------------------------------------

    public function test_creates_task_records(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', null)
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '1001',
                    'title'         => 'Fix login page',
                    'status'        => '5',
                    'closedDate'    => '2026-03-10T15:00:00+03:00',
                    'url'           => 'https://bitrix24.example.com/task/1001',
                    'responsibleId' => '777',
                ]),
                $this->makeTaskPayload([
                    'id'            => '1002',
                    'title'         => 'Add dashboard',
                    'status'        => '3',
                    'closedDate'    => null,
                    'url'           => 'https://bitrix24.example.com/task/1002',
                    'responsibleId' => '777',
                ]),
            ]);

        $count = ($this->action)();

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('tasks', 2);

        /** @var Task $completedTask */
        $completedTask = Task::query()->where('bitrix24_task_id', 1001)->first();
        $this->assertSame(TaskStatus::Completed, $completedTask->status);
        $this->assertFalse($completedTask->is_external);
        $this->assertSame(['responsible'], $completedTask->participation_roles);

        /** @var Task $inProgressTask */
        $inProgressTask = Task::query()->where('bitrix24_task_id', 1002)->first();
        $this->assertSame(TaskStatus::InProgress, $inProgressTask->status);
    }

    // ---------------------------------------------------------------------------
    // Role resolution
    // ---------------------------------------------------------------------------

    public function test_only_responsible_role(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '2001',
                    'createdBy'     => '5',
                    'responsibleId' => '777',
                ]),
            ]);

        ($this->action)();

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 2001)->first();
        $this->assertSame(['responsible'], $task->participation_roles);
    }

    public function test_only_auditor_role(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '2002',
                    'createdBy'     => '9',
                    'responsibleId' => '10',
                    'auditors'      => ['777', '12'],
                ]),
            ]);

        ($this->action)();

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 2002)->first();
        $this->assertSame(['auditor'], $task->participation_roles);
    }

    public function test_multiple_roles_are_collected_and_sorted(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '2003',
                    'createdBy'     => '777',
                    'responsibleId' => '777',
                    'accomplices'   => ['777'],
                    'auditors'      => ['777'],
                ]),
            ]);

        ($this->action)();

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 2003)->first();
        $this->assertSame(
            ['accomplice', 'auditor', 'creator', 'responsible'],
            $task->participation_roles,
        );
    }

    public function test_creator_plus_accomplice_roles(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '2004',
                    'createdBy'     => '777',
                    'responsibleId' => '8',
                    'accomplices'   => ['777', '9'],
                ]),
            ]);

        ($this->action)();

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 2004)->first();
        $this->assertSame(['accomplice', 'creator'], $task->participation_roles);
    }

    public function test_user_not_in_task_gets_empty_roles(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->andReturn([
                $this->makeTaskPayload([
                    'id'            => '2005',
                    'createdBy'     => '1',
                    'responsibleId' => '2',
                    'accomplices'   => ['3'],
                    'auditors'      => ['4'],
                ]),
            ]);

        ($this->action)();

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 2005)->first();
        $this->assertSame([], $task->participation_roles);
        $this->assertFalse($task->is_external);
    }

    // ---------------------------------------------------------------------------
    // Early return when bitrix24_user_id is not configured
    // ---------------------------------------------------------------------------

    public function test_returns_zero_when_no_user_id_configured(): void
    {
        // No Setting created — bitrix24_user_id is null
        $this->bitrix24Client->shouldNotReceive('getTasks');

        $count = ($this->action)();

        $this->assertSame(0, $count);
    }

    public function test_returns_zero_when_setting_has_null_user_id(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => null]);

        $this->bitrix24Client->shouldNotReceive('getTasks');

        $count = ($this->action)();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('tasks', 0);
    }

    // ---------------------------------------------------------------------------
    // Empty task list
    // ---------------------------------------------------------------------------

    public function test_returns_zero_when_get_tasks_returns_empty(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client->shouldReceive('getTasks')->once()->andReturn([]);

        $count = ($this->action)();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('tasks', 0);
    }

    // ---------------------------------------------------------------------------
    // Fallback path: user-wide getTasks throws → per-group
    // ---------------------------------------------------------------------------

    public function test_fallback_to_per_group_when_user_wide_fetch_throws(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        // First call (user-wide, groupId=null) throws
        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', null)
            ->andThrow(new RuntimeException('MEMBER filter not supported'));

        // getProjects returns two groups
        $this->bitrix24Client
            ->shouldReceive('getProjects')
            ->once()
            ->andReturn([
                ['id' => '10', 'name' => 'Group Alpha'],
                ['id' => '20', 'name' => 'Group Beta'],
            ]);

        // Per-group calls
        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', 10)
            ->andReturn([
                $this->makeTaskPayload(['id' => '5001', 'title' => 'Task from group Alpha']),
            ]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', 20)
            ->andReturn([
                $this->makeTaskPayload(['id' => '5002', 'title' => 'Task from group Beta']),
            ]);

        $count = ($this->action)();

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', ['bitrix24_task_id' => 5001]);
        $this->assertDatabaseHas('tasks', ['bitrix24_task_id' => 5002]);
    }

    public function test_fallback_deduplicates_tasks_appearing_in_multiple_groups(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '888']);

        // User-wide call fails
        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('888', null)
            ->andThrow(new RuntimeException('unsupported'));

        // Two groups, task 9999 appears in both
        $this->bitrix24Client
            ->shouldReceive('getProjects')
            ->once()
            ->andReturn([
                ['id' => '1', 'name' => 'Group One'],
                ['id' => '2', 'name' => 'Group Two'],
            ]);

        $duplicatedTask = $this->makeTaskPayload(['id' => '9999', 'title' => 'Shared task']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('888', 1)
            ->andReturn([$duplicatedTask]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('888', 2)
            ->andReturn([$duplicatedTask]);

        $count = ($this->action)();

        // After deduplication only one task record must exist
        $this->assertSame(1, $count);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_fallback_skips_group_when_per_group_fetch_throws(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', null)
            ->andThrow(new RuntimeException('user-wide failed'));

        $this->bitrix24Client
            ->shouldReceive('getProjects')
            ->once()
            ->andReturn([
                ['id' => '3', 'name' => 'Healthy Group'],
                ['id' => '4', 'name' => 'Broken Group'],
            ]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', 3)
            ->andReturn([
                $this->makeTaskPayload(['id' => '6001', 'title' => 'Healthy task']),
            ]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', 4)
            ->andThrow(new RuntimeException('group fetch failed'));

        $count = ($this->action)();

        // Only the task from the healthy group should be synced
        $this->assertSame(1, $count);
        $this->assertDatabaseHas('tasks', ['bitrix24_task_id' => 6001]);
    }

    public function test_fallback_returns_zero_when_get_projects_throws(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
            ->with('777', null)
            ->andThrow(new RuntimeException('user-wide failed'));

        $this->bitrix24Client
            ->shouldReceive('getProjects')
            ->once()
            ->andThrow(new RuntimeException('sonet_group.get failed'));

        $count = ($this->action)();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('tasks', 0);
    }

    // ---------------------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------------------

    public function test_upserts_existing_task_on_repeat_sync(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);

        $payload = $this->makeTaskPayload([
            'id'    => '3001',
            'title' => 'Original title',
        ]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->twice()
            ->andReturn([$payload], [array_merge($payload, ['title' => 'Updated title'])]);

        ($this->action)();
        ($this->action)();

        $this->assertDatabaseCount('tasks', 1);

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 3001)->first();
        $this->assertSame('Updated title', $task->title);
    }

    // ---------------------------------------------------------------------------
    // setUp
    // ---------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->action = new SyncBitrix24Tasks(
            bitrix24Client: $this->bitrix24Client,
        );
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     statusComplete: string,
     *     groupId: string,
     *     group: array{id: string, name: string},
     *     closedDate: string|null,
     *     url: string,
     *     createdBy: string,
     *     responsibleId: string,
     *     accomplices: list<string>,
     *     auditors: list<string>
     * }
     */
    private function makeTaskPayload(array $overrides): array
    {
        /** @var array{
         *     id: string,
         *     title: string,
         *     status: string,
         *     statusComplete: string,
         *     groupId: string,
         *     group: array{id: string, name: string},
         *     closedDate: string|null,
         *     url: string,
         *     createdBy: string,
         *     responsibleId: string,
         *     accomplices: list<string>,
         *     auditors: list<string>
         * } $payload
         */
        $payload = array_merge([
            'id'             => '1',
            'title'          => 'Task',
            'status'         => '3',
            'statusComplete' => '0',
            'groupId'        => '5',
            'group'          => ['id' => '5', 'name' => 'Project Alpha'],
            'closedDate'     => null,
            'url'            => 'https://bitrix24.example.com/task/1',
            'createdBy'      => '1',
            'responsibleId'  => '1',
            'accomplices'    => [],
            'auditors'       => [],
        ], $overrides);

        return $payload;
    }
}
