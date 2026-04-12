<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Report\Queries\HasDataForDateRange;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HasDataForDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private HasDataForDateRange $query;

    public function test_returns_true_when_commits_exist_in_range(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
        ]);

        $result = ($this->query)(new DateRange('2026-03-09', '2026-03-11'));

        $this->assertTrue($result);
    }

    public function test_returns_true_when_tasks_exist_in_range(): void
    {
        Task::factory()->create([
            'status_changed_at' => '2026-03-10 10:00:00',
        ]);

        $result = ($this->query)(new DateRange('2026-03-09', '2026-03-11'));

        $this->assertTrue($result);
    }

    public function test_returns_false_when_no_data_exists(): void
    {
        $result = ($this->query)(new DateRange('2026-03-09', '2026-03-11'));

        $this->assertFalse($result);
    }

    public function test_returns_false_when_data_outside_range(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-01 14:00:00',
        ]);

        $result = ($this->query)(new DateRange('2026-03-09', '2026-03-11'));

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new HasDataForDateRange();
    }
}
