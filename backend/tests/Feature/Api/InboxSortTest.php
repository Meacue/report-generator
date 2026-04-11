<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Models\MatchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_desc_returns_newer_branch_first(): void
    {
        $older = Branch::factory()->create(['parsed_date' => '2026-03-10']);
        $newer = Branch::factory()->create(['parsed_date' => '2026-03-15']);

        MatchResult::factory()->probable()->create(['branch_id' => $older->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $newer->id]);

        $response = $this->getJson('/api/inbox?sort_direction=desc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }

    public function test_sort_asc_returns_older_branch_first(): void
    {
        $older = Branch::factory()->create(['parsed_date' => '2026-03-10']);
        $newer = Branch::factory()->create(['parsed_date' => '2026-03-15']);

        MatchResult::factory()->probable()->create(['branch_id' => $older->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $newer->id]);

        $response = $this->getJson('/api/inbox?sort_direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_default_sort_without_parameter_returns_newer_branch_first(): void
    {
        $older = Branch::factory()->create(['parsed_date' => '2026-03-10']);
        $newer = Branch::factory()->create(['parsed_date' => '2026-03-15']);

        MatchResult::factory()->probable()->create(['branch_id' => $older->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $newer->id]);

        $response = $this->getJson('/api/inbox');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }

    public function test_branches_without_parsed_date_appear_last_in_desc_sort(): void
    {
        $withDate = Branch::factory()->create(['parsed_date' => '2026-03-15']);
        $withoutDate = Branch::factory()->withoutDate()->create();

        MatchResult::factory()->probable()->create(['branch_id' => $withDate->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $withoutDate->id]);

        $response = $this->getJson('/api/inbox?sort_direction=desc');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $withDate->id);
        $response->assertJsonPath('data.1.id', $withoutDate->id);
    }

    public function test_branches_without_parsed_date_appear_last_in_asc_sort(): void
    {
        $withDate = Branch::factory()->create(['parsed_date' => '2026-03-10']);
        $withoutDate = Branch::factory()->withoutDate()->create();

        MatchResult::factory()->probable()->create(['branch_id' => $withDate->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $withoutDate->id]);

        $response = $this->getJson('/api/inbox?sort_direction=asc');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $withDate->id);
        $response->assertJsonPath('data.1.id', $withoutDate->id);
    }

    public function test_invalid_sort_direction_falls_back_to_desc(): void
    {
        $older = Branch::factory()->create(['parsed_date' => '2026-03-10']);
        $newer = Branch::factory()->create(['parsed_date' => '2026-03-15']);

        MatchResult::factory()->probable()->create(['branch_id' => $older->id]);
        MatchResult::factory()->probable()->create(['branch_id' => $newer->id]);

        $response = $this->getJson('/api/inbox?sort_direction=invalid_value');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }
}
