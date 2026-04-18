<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\ProjectMapping;
use App\Domain\Settings\Models\Setting;
use App\Domain\Sync\Actions\SyncBitrix24Tasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncBitrix24TasksTest extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private SyncBitrix24Tasks $action;

    public function test_creates_task_records(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

        $this->bitrix24Client
            ->shouldReceive('getTasks')
            ->once()
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

    public function test_only_responsible_role(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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

    public function test_returns_zero_when_no_user_id_configured(): void
    {
        // No Setting created — bitrix24_user_id is null
        $this->bitrix24Client->shouldNotReceive('getTasks');

        $count = ($this->action)();

        $this->assertSame(0, $count);
    }

    public function test_upserts_existing_task_on_repeat_sync(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => '777']);
        ProjectMapping::factory()->create(['bitrix24_project_id' => 5]);

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->action = new SyncBitrix24Tasks(
            bitrix24Client: $this->bitrix24Client,
        );
    }

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
