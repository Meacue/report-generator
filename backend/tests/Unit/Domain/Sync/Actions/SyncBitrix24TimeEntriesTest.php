<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\DTOs\TimeEntryData;
use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;
use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\SyncBitrix24TimeEntries;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncBitrix24TimeEntriesTest extends TestCase
{
    use RefreshDatabase;

    private Bitrix24ClientInterface&MockInterface $bitrix24Client;

    private SyncBitrix24TimeEntries $action;

    public function test_upserts_entries_by_bitrix24_entry_id(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => 777]);

        $entry = $this->makeEntry(entryId: 5001, taskId: 1001, seconds: 3600);

        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn([$entry]);

        $period = DateRange::lastDays(7);
        $count = ($this->action)($period);

        $this->assertSame(1, $count);
        $this->assertDatabaseCount('task_time_entries', 1);
        $this->assertDatabaseHas('task_time_entries', [
            'bitrix24_entry_id' => 5001,
            'bitrix24_task_id'  => 1001,
            'seconds'           => 3600,
        ]);

        // Run again with same entry — no duplicate
        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn([$entry]);

        ($this->action)($period);

        $this->assertDatabaseCount('task_time_entries', 1);
    }

    public function test_updates_existing_entry_on_repeat_sync(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => 777]);

        $period = DateRange::lastDays(7);

        $original = $this->makeEntry(entryId: 6001, taskId: 2001, seconds: 1800, comment: 'First');
        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn([$original]);
        ($this->action)($period);

        // Same entry ID, updated seconds and comment
        $updated = $this->makeEntry(entryId: 6001, taskId: 2001, seconds: 7200, comment: 'Updated');
        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn([$updated]);
        ($this->action)($period);

        $this->assertDatabaseCount('task_time_entries', 1);

        /** @var TimeEntry $entry */
        $entry = TimeEntry::query()->where('bitrix24_entry_id', 6001)->first();
        $this->assertSame(7200, $entry->seconds);
        $this->assertSame('Updated', $entry->comment);
    }

    public function test_returns_zero_when_bitrix24_user_id_unset(): void
    {
        // No Setting — bitrix24_user_id is null
        $this->bitrix24Client->shouldNotReceive('getTimeEntries');

        $period = DateRange::lastDays(7);
        $count = ($this->action)($period);

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('task_time_entries', 0);
    }

    public function test_respects_period_from_to(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => 777]);

        $from = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
        $to = CarbonImmutable::parse('2026-01-07 23:59:59', 'UTC');
        $period = DateRange::between($from, $to);

        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->withArgs(function (string $userId, CarbonImmutable $passedFrom, CarbonImmutable $passedTo) use ($from, $to): bool {
                return $userId === '777'
                    && $passedFrom->equalTo($from)
                    && $passedTo->equalTo($to);
            })
            ->andReturn([]);

        $count = ($this->action)($period);

        $this->assertSame(0, $count);
    }

    public function test_processes_generator_lazily_without_accumulating(): void
    {
        Setting::factory()->create(['bitrix24_user_id' => 777]);

        $entries = [
            $this->makeEntry(entryId: 7001, taskId: 3001, seconds: 900),
            $this->makeEntry(entryId: 7002, taskId: 3001, seconds: 1800),
            $this->makeEntry(entryId: 7003, taskId: 3002, seconds: 3600),
        ];

        $this->bitrix24Client
            ->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn($this->yieldEntries($entries));

        $period = DateRange::lastDays(7);
        $count = ($this->action)($period);

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('task_time_entries', 3);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitrix24Client = Mockery::mock(Bitrix24ClientInterface::class);
        $this->app->instance(Bitrix24ClientInterface::class, $this->bitrix24Client);

        $this->action = new SyncBitrix24TimeEntries(
            bitrix24Client: $this->bitrix24Client,
        );
    }

    private function makeEntry(
        int $entryId,
        int $taskId,
        int $seconds,
        ?string $comment = null,
    ): TimeEntryData {
        return new TimeEntryData(
            bitrix24EntryId: $entryId,
            bitrix24TaskId: $taskId,
            bitrix24UserId: '777',
            seconds: $seconds,
            comment: $comment,
            trackedAt: CarbonImmutable::parse('2026-01-05 10:00:00', 'UTC'),
            sourceCreatedAt: CarbonImmutable::parse('2026-01-05 09:00:00', 'UTC'),
        );
    }

    /**
     * @param  list<TimeEntryData>  $entries
     * @return \Generator<int, TimeEntryData>
     */
    private function yieldEntries(array $entries): \Generator
    {
        foreach ($entries as $entry) {
            yield $entry;
        }
    }
}
