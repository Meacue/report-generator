<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Queries;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Report\Queries\GetCommitsForDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetCommitsForDateTest extends TestCase
{
    use RefreshDatabase;

    private GetCommitsForDate $query;

    public function test_returns_commits_for_given_date(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
        ]);

        $result = ($this->query)('2026-03-10');

        $this->assertCount(1, $result);
    }

    public function test_returns_empty_collection_when_no_commits(): void
    {
        $result = ($this->query)('2026-03-10');

        $this->assertCount(0, $result);
    }

    public function test_does_not_return_commits_from_other_dates(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-09 14:00:00',
        ]);

        $result = ($this->query)('2026-03-10');

        $this->assertCount(0, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetCommitsForDate();
    }
}
