<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\ServiceUnavailableException;
use App\Services\LLM\LlmProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
        Route::post('/api/_test/service-unavailable', static function (): never {
            throw new ServiceUnavailableException('GitLab');
        });

        $response = $this->postJson('/api/_test/service-unavailable');

        $response->assertStatus(503);
        $response->assertJsonStructure(['error', 'service', 'retry_after']);
    }

    public function test_service_unavailable_response_contains_retry_after(): void
    {
        Route::post('/api/_test/service-unavailable', static function (): never {
            throw new ServiceUnavailableException('Bitrix24');
        });

        $response = $this->postJson('/api/_test/service-unavailable');

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
        $this->app->instance(LlmProviderInterface::class, $mockLlm);
    }
}
