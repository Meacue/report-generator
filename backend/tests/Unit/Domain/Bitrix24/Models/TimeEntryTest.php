<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bitrix24\Models;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Models\TimeEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_time_entry_with_factory(): void
    {
        // GIVEN-WHEN: create a time entry through the factory.
        $entry = TimeEntry::factory()->create();

        // THEN: record exists in the database with the generated primary key.
        $this->assertDatabaseHas('task_time_entries', ['id' => $entry->id]);
        $this->assertNotNull($entry->bitrix24_entry_id);
    }

    public function test_casts_fields_correctly(): void
    {
        // GIVEN: a stored time entry with explicit scalar values.
        $entry = TimeEntry::factory()->create([
            'seconds'    => 3600,
            'tracked_at' => '2026-04-10 12:30:00',
        ]);

        // WHEN: reload it from the database.
        $fresh = TimeEntry::query()->findOrFail($entry->id);

        // THEN: casts convert DB values into native PHP types.
        $this->assertIsInt($fresh->seconds);
        $this->assertSame(3600, $fresh->seconds);
        $this->assertInstanceOf(Carbon::class, $fresh->tracked_at);
        $this->assertInstanceOf(Carbon::class, $fresh->synced_at);
    }

    public function test_unique_bitrix24_entry_id_constraint(): void
    {
        // GIVEN: one entry with a known bitrix24_entry_id.
        TimeEntry::factory()->create(['bitrix24_entry_id' => 424242]);

        // THEN: inserting another with the same key throws a query exception.
        $this->expectException(QueryException::class);

        // WHEN: attempt to insert a duplicate.
        TimeEntry::factory()->create(['bitrix24_entry_id' => 424242]);
    }

    public function test_belongs_to_task_relation(): void
    {
        // GIVEN: a task and a time entry joined by the business key.
        $task = Task::factory()->create(['bitrix24_task_id' => 555_001]);
        $entry = TimeEntry::factory()->create(['bitrix24_task_id' => 555_001]);

        // WHEN: access the relation.
        $related = $entry->task;

        // THEN: the related task is resolved.
        $this->assertNotNull($related);
        $this->assertSame($task->id, $related->id);
    }

    public function test_belongs_to_task_returns_null_when_task_absent(): void
    {
        // GIVEN: a time entry whose task has not yet been synced.
        $entry = TimeEntry::factory()->create(['bitrix24_task_id' => 999_999]);

        // WHEN-THEN: the relation resolves to null without raising.
        $this->assertNull($entry->task);
    }
}
