<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Sync\Actions\EnsureTasksForPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class EnsureTasksForPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private EnsureTasksForPeriod $action;

    public function test_skips_existing_tasks(): void
    {
        // GIVEN: 3 task IDs, 2 already exist in the DB
        Task::factory()->create(['bitrix24_task_id' => 100]);
        Task::factory()->create(['bitrix24_task_id' => 200]);

        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(300)
            ->andReturn($this->makeTaskPayload('300'));

        // WHEN: the action is invoked with all 3 IDs
        $count = ($this->action)([100, 200, 300]);

        // THEN: API is called exactly once (only for the missing ID)
        $this->assertSame(1, $count);
    }

    public function test_creates_external_task_when_get_succeeds(): void
    {
        // GIVEN: tryGetTask returns a valid payload for task 500
        $payload = $this->makeTaskPayload('500', [
            'title'   => 'External task title',
            'status'  => '3',
            'groupId' => '10',
            'group'   => ['id' => '10', 'name' => 'SomeProject'],
            'url'     => '/tasks/500/',
        ]);

        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(500)
            ->andReturn($payload);

        // WHEN: the action is invoked
        $count = ($this->action)([500]);

        // THEN: a Task record is created with is_external=true, participation_roles=[], and the returned title
        $this->assertSame(1, $count);
        $this->assertDatabaseHas('tasks', [
            'bitrix24_task_id' => 500,
            'title'            => 'External task title',
            'is_external'      => true,
        ]);

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 500)->first();
        $this->assertTrue($task->is_external);
        $this->assertSame([], $task->participation_roles);
        $this->assertSame('External task title', $task->title);
    }

    public function test_creates_stub_when_get_returns_null(): void
    {
        // GIVEN: tryGetTask returns null (403/404 scenario)
        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(999)
            ->andReturn(null);

        // WHEN: the action is invoked
        $count = ($this->action)([999]);

        // THEN: a stub Task record is created with title=null, is_external=true, participation_roles=[]
        $this->assertSame(1, $count);

        /** @var Task $task */
        $task = Task::query()->where('bitrix24_task_id', 999)->first();
        $this->assertNotNull($task);
        $this->assertNull($task->title);
        $this->assertTrue($task->is_external);
        $this->assertSame([], $task->participation_roles);
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertNull($task->project_id);
    }

    public function test_skips_task_on_runtime_exception(): void
    {
        // GIVEN: tryGetTask throws for task 111, succeeds for task 222
        Log::spy();

        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(111)
            ->andThrow(new RuntimeException('Connection failed'));

        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(222)
            ->andReturn(null);

        // WHEN: the action is invoked with both IDs
        $count = ($this->action)([111, 222]);

        // THEN: warning is logged for task 111; task 222 is still processed;
        //       count reflects only the successfully processed task
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('EnsureTasksForPeriod: could not fetch task from Bitrix24', Mockery::on(
                fn (array $ctx) => $ctx['task_id'] === 111,
            ));

        $this->assertSame(1, $count);
        $this->assertDatabaseMissing('tasks', ['bitrix24_task_id' => 111]);
        $this->assertDatabaseHas('tasks', ['bitrix24_task_id' => 222]);
    }

    public function test_idempotent_on_repeat_call(): void
    {
        // GIVEN: tryGetTask returns a stub (null) for task 777
        $this->bitrix24Client
            ->shouldReceive('tryGetTask')
            ->once()
            ->with(777)
            ->andReturn(null);

        // WHEN: the action is called twice with the same ID
        ($this->action)([777]);
        $secondCount = ($this->action)([777]);

        // THEN: only one DB row exists; second call returns 0 (no API call made)
        $this->assertSame(0, $secondCount);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_returns_zero_when_no_missing_ids(): void
    {
        // GIVEN: all 3 IDs already exist in the DB
        Task::factory()->create(['bitrix24_task_id' => 10]);
        Task::factory()->create(['bitrix24_task_id' => 20]);
        Task::factory()->create(['bitrix24_task_id' => 30]);

        $this->bitrix24Client->shouldNotReceive('tryGetTask');

        // WHEN: the action is invoked with all 3 existing IDs
        $count = ($this->action)([10, 20, 30]);

        // THEN: API is never called, and 0 is returned
        $this->assertSame(0, $count);
    }

    public function test_returns_zero_when_empty_list_given(): void
    {
        // GIVEN: an empty list of task IDs
        $this->bitrix24Client->shouldNotReceive('tryGetTask');

        // WHEN: the action is invoked with []
        $count = ($this->action)([]);

        // THEN: 0 is returned immediately
        $this->assertSame(0, $count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->action = new EnsureTasksForPeriod(
            bitrix24Client: $this->bitrix24Client,
        );
    }

    /**
     * Build a minimal normalized task payload as returned by Bitrix24Client.
     *
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
    private function makeTaskPayload(string $id, array $overrides = []): array
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
            'id'             => $id,
            'title'          => 'Task ' . $id,
            'status'         => '3',
            'statusComplete' => '0',
            'groupId'        => '5',
            'group'          => ['id' => '5', 'name' => 'DefaultProject'],
            'closedDate'     => null,
            'url'            => '/tasks/' . $id . '/',
            'createdBy'      => '1',
            'responsibleId'  => '1',
            'accomplices'    => [],
            'auditors'       => [],
        ], $overrides);

        return $payload;
    }
}
