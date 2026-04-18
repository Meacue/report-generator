<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Report\Queries\GetTaskTimeBreakdown;
use App\Domain\Settings\Models\Setting;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetTaskTimeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private GetTaskTimeBreakdown $query;

    public function test_sums_seconds_per_task_for_period(): void
    {
        // GIVEN: 3 entries for task A, 1 entry for task B inside the period,
        // and 1 entry for task A outside the period.
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
            'tracked_at'       => '2026-04-11 10:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => $userId,
            'seconds'          => 900,
            'tracked_at'       => '2026-04-12 11:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57840,
            'bitrix24_user_id' => $userId,
            'seconds'          => 7200,
            'tracked_at'       => '2026-04-15 08:00:00',
        ]);
        // Outside period — should not be counted.
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => $userId,
            'seconds'          => 9999,
            'tracked_at'       => '2026-03-01 08:00:00',
        ]);

        // WHEN:
        $result = ($this->query)($period, $userId);

        // THEN: task A = 3600+1800+900 = 6300, task B = 7200.
        $this->assertSame([57706 => 6300, 57840 => 7200], $result);
    }

    public function test_filters_by_user_id(): void
    {
        // GIVEN: same task id, two different users.
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 100,
            'bitrix24_user_id' => '1',
            'seconds'          => 3600,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 100,
            'bitrix24_user_id' => '2',
            'seconds'          => 1000,
            'tracked_at'       => '2026-04-10 10:00:00',
        ]);

        // WHEN: query for user '1'.
        $result = ($this->query)($period, '1');

        // THEN: only user '1' entry is counted.
        $this->assertSame([100 => 3600], $result);
    }

    public function test_returns_empty_array_when_no_entries(): void
    {
        // GIVEN: no time entries exist.
        $period = new DateRange('2026-04-01', '2026-04-30');

        // WHEN:
        $result = ($this->query)($period, '99');

        // THEN:
        $this->assertSame([], $result);
    }

    public function test_uses_setting_user_id_when_userId_not_provided(): void
    {
        // GIVEN: a Setting with a known bitrix24_user_id.
        Setting::factory()->create(['bitrix24_user_id' => '77']);
        $period = new DateRange('2026-04-01', '2026-04-30');

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 200,
            'bitrix24_user_id' => '77',
            'seconds'          => 500,
            'tracked_at'       => '2026-04-05 08:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 200,
            'bitrix24_user_id' => '99',
            'seconds'          => 999,
            'tracked_at'       => '2026-04-05 09:00:00',
        ]);

        // WHEN: no explicit userId.
        $result = ($this->query)($period);

        // THEN: only entries for user '77' (from settings) are counted.
        $this->assertSame([200 => 500], $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetTaskTimeBreakdown();
    }
}
