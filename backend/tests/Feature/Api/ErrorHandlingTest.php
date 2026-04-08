<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\ServiceUnavailableException;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\MatchResult;
use App\Models\Report;
use App\Models\Task;
use App\Services\Narrative\NarrativeService;
use App\Services\Narrative\NarrativeServiceInterface;
use App\Services\Report\ReportBuilderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_health_check_returns_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_no_data_exception_returns_422(): void
    {
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_no_data_exception_contains_meaningful_message(): void
    {
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(422);
        $errorMessage = $response->json('error');
        $this->assertIsString($errorMessage);
        $this->assertNotEmpty($errorMessage);
    }

    public function test_service_unavailable_returns_503(): void
    {
        $this->app->bind(ReportBuilderInterface::class, static function (): ReportBuilderInterface {
            return new class () implements ReportBuilderInterface {
                public function generate(string $type, string $dateFrom, string $dateTo): Report
                {
                    throw new ServiceUnavailableException('GitLab');
                }

                /**
                 * @return array{
                 *     id: int,
                 *     type: string,
                 *     date_from: string,
                 *     date_to: string,
                 *     status: string,
                 *     days: array<int, array{
                 *         date: string,
                 *         narrative: string|null,
                 *         source: string,
                 *         is_edited: bool,
                 *         tasks: array<int, array{
                 *             id: int|null,
                 *             title: string,
                 *             project_name: string|null,
                 *             narrative: string|null,
                 *             is_edited: bool
                 *         }>
                 *     }>
                 * }
                 */
                public function getPreview(Report $report): array
                {
                    return [
                        'id'        => $report->id,
                        'type'      => $report->type->value,
                        'date_from' => $report->date_from->format('Y-m-d'),
                        'date_to'   => $report->date_to->format('Y-m-d'),
                        'status'    => $report->status->value,
                        'days'      => [],
                    ];
                }
            };
        });

        // We need data in DB so NoDataException is not thrown before ReportBuilder::generate()
        $task = Task::factory()->create([
            'status_changed_at' => '2026-03-10 10:00:00',
        ]);

        $branch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
        ]);

        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(503);
        $response->assertJsonStructure(['error', 'service', 'retry_after']);
    }

    public function test_service_unavailable_response_contains_retry_after(): void
    {
        $this->app->bind(ReportBuilderInterface::class, static function (): ReportBuilderInterface {
            return new class () implements ReportBuilderInterface {
                public function generate(string $type, string $dateFrom, string $dateTo): Report
                {
                    throw new ServiceUnavailableException('Bitrix24');
                }

                /**
                 * @return array{
                 *     id: int,
                 *     type: string,
                 *     date_from: string,
                 *     date_to: string,
                 *     status: string,
                 *     days: array<int, array{
                 *         date: string,
                 *         narrative: string|null,
                 *         source: string,
                 *         is_edited: bool,
                 *         tasks: array<int, array{
                 *             id: int|null,
                 *             title: string,
                 *             project_name: string|null,
                 *             narrative: string|null,
                 *             is_edited: bool
                 *         }>
                 *     }>
                 * }
                 */
                public function getPreview(Report $report): array
                {
                    return [
                        'id'        => $report->id,
                        'type'      => $report->type->value,
                        'date_from' => $report->date_from->format('Y-m-d'),
                        'date_to'   => $report->date_to->format('Y-m-d'),
                        'status'    => $report->status->value,
                        'days'      => [],
                    ];
                }
            };
        });

        $task = Task::factory()->create([
            'status_changed_at' => '2026-03-10 10:00:00',
        ]);

        $branch = Branch::factory()->create();
        MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 14:00:00',
        ]);

        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(503);
        $this->assertSame(300, $response->json('retry_after'));
    }

    public function test_generate_report_validates_required_fields(): void
    {
        $response = $this->postJson('/api/reports/generate', []);

        $response->assertStatus(422);
    }

    public function test_generate_report_validates_date_format(): void
    {
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => 'not-a-date',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(422);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $mockLlm = new MockLlmProvider();
        $this->app->bind(NarrativeServiceInterface::class, static function () use ($mockLlm): NarrativeService {
            return new NarrativeService($mockLlm);
        });
    }
}
