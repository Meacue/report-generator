<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetTaskTimeTimelineTest extends TestCase
{
    use RefreshDatabase;

    private GetTaskTimeTimeline $query;

    public function test_groups_by_date_and_task(): void
    {
        // GIVEN: two tasks spread over two days.
        $userId = '42';
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => $userId,
            'seconds'          => 3600,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => $userId,
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-10 14:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57840,
            'bitrix24_user_id' => $userId,
            'seconds'          => 900,
            'tracked_at'       => '2026-04-10 16:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => $userId,
            'seconds'          => 7200,
            'tracked_at'       => '2026-04-15 08:00:00',
        ]);

        // WHEN:
        $result = ($this->query)($period, $userId);

        // THEN: dates are the top-level keys.
        $this->assertArrayHasKey('2026-04-10', $result);
        $this->assertArrayHasKey('2026-04-15', $result);

        // On 2026-04-10: task 57706 = 3600+1800 = 5400, task 57840 = 900.
        $this->assertSame(5400, $result['2026-04-10'][57706]);
        $this->assertSame(900, $result['2026-04-10'][57840]);

        // On 2026-04-15: task 57706 = 7200.
        $this->assertSame(7200, $result['2026-04-15'][57706]);
    }

    public function test_result_is_sorted_by_date_ascending(): void
    {
        // GIVEN: entries on three dates, inserted in reverse order.
        $userId = '5';
        $period = new DateRange('2026-04-01', '2026-04-30');

        foreach (['2026-04-20', '2026-04-05', '2026-04-12'] as $date) {
            TimeEntry::factory()->create([
                'bitrix24_task_id' => 100,
                'bitrix24_user_id' => $userId,
                'seconds'          => 60,
                'tracked_at'       => $date . ' 09:00:00',
            ]);
        }

        // WHEN:
        $result = ($this->query)($period, $userId);

        // THEN: keys must be in ascending date order.
        $this->assertSame(['2026-04-05', '2026-04-12', '2026-04-20'], array_keys($result));
    }

    public function test_filters_by_user_id(): void
    {
        // GIVEN: same date, same task, two users.
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 300,
            'bitrix24_user_id' => '10',
            'seconds'          => 100,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 300,
            'bitrix24_user_id' => '20',
            'seconds'          => 999,
            'tracked_at'       => '2026-04-10 10:00:00',
        ]);

        // WHEN: query for user '10' only.
        $result = ($this->query)($period, '10');

        $this->assertSame([300 => 100], $result['2026-04-10']);
    }

    public function test_excludes_entries_outside_period(): void
    {
        // GIVEN: one entry inside period, one outside.
        $userId = '1';
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 400,
            'bitrix24_user_id' => $userId,
            'seconds'          => 500,
            'tracked_at'       => '2026-04-15 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 400,
            'bitrix24_user_id' => $userId,
            'seconds'          => 999,
            'tracked_at'       => '2026-03-01 09:00:00',
        ]);

        // WHEN:
        $result = ($this->query)($period, $userId);

        // THEN: only the entry inside the period is present.
        $this->assertCount(1, $result);
        $this->assertSame(500, $result['2026-04-15'][400]);
    }

    public function test_returns_empty_array_when_no_entries(): void
    {
        // GIVEN: no entries exist.
        $period = new DateRange('2026-04-01', '2026-04-30');

        // WHEN/THEN:
        $this->assertSame([], ($this->query)($period, '999'));
    }

    public function test_uses_setting_user_id_when_userId_not_provided(): void
    {
        // GIVEN: Setting with bitrix24_user_id = '55'.
        Setting::factory()->create(['bitrix24_user_id' => '55']);
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 500,
            'bitrix24_user_id' => '55',
            'seconds'          => 300,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);

        // WHEN: no explicit userId.
        $result = ($this->query)($period);

        // THEN: entry for user '55' is found.
        $this->assertSame([500 => 300], $result['2026-04-10']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetTaskTimeTimeline();
    }
}
