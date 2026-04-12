<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Settings\Models\Setting;
use App\Domain\Bitrix24\Models\Task;
use App\Services\LLM\LlmProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

class ReportGenerationE2ETest extends TestCase
{
    use RefreshDatabase;

    private MockLlmProvider $mockLlm;

    public function test_full_report_cycle(): void
    {
        Setting::factory()->create([
            'developer_name'     => 'Тестов Тест Тестович',
            'developer_position' => 'QA Engineer',
        ]);

        $task = Task::factory()->create([
            'title'             => 'Реализация модуля отчётов',
            'project_name'      => 'ReportGenerator',
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
            'message'      => 'feat: implement report builder service',
        ]);

        // Step 1: Generate report
        $generateResponse = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $generateResponse->assertStatus(201);
        $generateResponse->assertJsonStructure(['data' => ['id']]);

        /** @var int $reportId */
        $reportId = $generateResponse->json('data.id');

        $report = Report::findOrFail($reportId);
        $this->assertSame('generated', $report->status->value);

        // Step 2: Preview the report
        $previewResponse = $this->getJson("/api/reports/{$reportId}/preview");

        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'date_from',
                'date_to',
                'status',
                'days',
                'tasks',
            ],
        ]);

        /** @var array{id: int, type: string, date_from: string, date_to: string, status: string, days: list<array{date: string, narrative: string|null, source: string, is_edited: bool, tasks: list<array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}>}>} $previewData */
        $previewData = $previewResponse->json('data');
        $this->assertNotEmpty($previewData['days']);

        // Find first day with tasks for subsequent steps
        $taskId = null;
        foreach ($previewData['days'] as $day) {
            if (! empty($day['tasks'])) {
                $taskId = $day['tasks'][0]['id'];
                break;
            }
        }

        $this->assertNotNull($taskId, 'At least one day should have tasks');

        // Step 3: Edit task narrative
        $editResponse = $this->putJson("/api/reports/{$reportId}/task/{$taskId}", [
            'narrative' => 'Ручное описание задачи для тестирования.',
        ]);

        $editResponse->assertStatus(200);
        $editResponse->assertJson(['message' => 'Updated']);

        $reportTask = ReportTask::where('report_id', $reportId)
            ->where('task_id', $taskId)
            ->firstOrFail();

        $this->assertSame('Ручное описание задачи для тестирования.', $reportTask->narrative);
        $this->assertTrue($reportTask->is_edited);

        // Travel forward so regenerate history entry gets a later changed_at timestamp
        $this->travel(2)->seconds();

        // Step 4: Regenerate task narrative via LLM
        $regenerateResponse = $this->postJson("/api/reports/{$reportId}/task/{$taskId}/regenerate");

        $regenerateResponse->assertStatus(200);
        $regenerateResponse->assertJsonStructure(['data']);

        $reportTask->refresh();
        $this->assertSame($this->mockLlm->narrativeText, $reportTask->narrative);
        $this->assertFalse($reportTask->is_edited);

        // Step 5: Undo should restore the manual edit narrative (saved in history by regenerate step)
        $undoResponse = $this->postJson("/api/reports/{$reportId}/task/{$taskId}/undo");

        $undoResponse->assertStatus(200);
        $undoResponse->assertJsonStructure(['data']);

        $reportTask->refresh();
        $this->assertSame('Ручное описание задачи для тестирования.', $reportTask->narrative);

        // Step 6: Export the report
        $exportResponse = $this->get("/api/reports/{$reportId}/export");

        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $report->refresh();
        $this->assertSame('exported', $report->status->value);
    }

    public function test_report_generation_with_no_data_returns_error(): void
    {
        $response = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_export_prompt_returns_text_file(): void
    {
        $task = Task::factory()->create([
            'title'             => 'Задача для промпта',
            'project_name'      => 'TestProject',
            'status_changed_at' => '2026-03-10 10:00:00',
        ]);

        $branch = Branch::factory()->create();

        MatchResult::factory()->create([
            'branch_id' => $branch->id,
            'task_id'   => $task->id,
        ]);

        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 12:00:00',
            'message'      => 'feat: add report export',
        ]);

        $generateResponse = $this->postJson('/api/reports/generate', [
            'type'      => 'daily',
            'date_from' => '2026-03-10',
            'date_to'   => '2026-03-10',
        ]);

        $generateResponse->assertStatus(201);

        /** @var int $reportId */
        $reportId = $generateResponse->json('data.id');

        $promptResponse = $this->get("/api/reports/{$reportId}/export-prompt");

        $promptResponse->assertStatus(200);

        $contentType = $promptResponse->headers->get('content-type') ?? '';
        $this->assertStringContainsString('text/plain', $contentType);

        $content = $promptResponse->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('СИСТЕМНЫЙ ПРОМПТ', $content);
        $this->assertStringContainsString('ЗАДАЧИ', $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlm = new MockLlmProvider();
        $this->app->instance(LlmProviderInterface::class, $this->mockLlm);
    }
}
