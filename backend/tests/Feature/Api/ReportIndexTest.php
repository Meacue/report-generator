<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_reports(): void
    {
        Report::factory()->count(3)->create();

        $response = $this->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'date_from',
                    'date_to',
                    'status',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    public function test_index_returns_empty_when_no_reports(): void
    {
        $response = $this->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    public function test_index_default_sort_is_newest_first(): void
    {
        $oldest = Report::factory()->create(['created_at' => '2026-01-01 10:00:00']);
        $middle = Report::factory()->create(['created_at' => '2026-02-01 10:00:00']);
        $newest = Report::factory()->create(['created_at' => '2026-03-01 10:00:00']);

        $response = $this->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $middle->id);
        $response->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_index_sort_asc_returns_oldest_first(): void
    {
        $oldest = Report::factory()->create(['created_at' => '2026-01-01 10:00:00']);
        $middle = Report::factory()->create(['created_at' => '2026-02-01 10:00:00']);
        $newest = Report::factory()->create(['created_at' => '2026-03-01 10:00:00']);

        $response = $this->getJson('/api/reports?sort_direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $oldest->id);
        $response->assertJsonPath('data.1.id', $middle->id);
        $response->assertJsonPath('data.2.id', $newest->id);
    }

    public function test_index_respects_per_page(): void
    {
        Report::factory()->count(5)->create();

        $response = $this->getJson('/api/reports?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonPath('meta.total', 5);
    }

    public function test_index_returns_correct_fields(): void
    {
        $report = Report::factory()->weekly()->generated()->create([
            'created_at' => '2026-03-10 12:00:00',
        ]);

        $response = $this->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $report->id);
        $response->assertJsonPath('data.0.type', $report->type->value);
        $response->assertJsonPath('data.0.date_from', $report->date_from->format('Y-m-d'));
        $response->assertJsonPath('data.0.date_to', $report->date_to->format('Y-m-d'));
        $response->assertJsonPath('data.0.status', $report->status->value);
    }

    public function test_index_serializes_enums_as_strings(): void
    {
        Report::factory()->weekly()->generated()->create();

        $response = $this->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonPath('data.0.type', 'weekly');
        $response->assertJsonPath('data.0.status', 'generated');
    }
}
