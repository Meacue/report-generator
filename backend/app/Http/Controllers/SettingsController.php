<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Narrative\Enums\LlmProvider;
use App\Http\Requests\UpdateSettingsRequest;
use App\Domain\Settings\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var Setting|null $settings */
        $settings = Setting::query()->first();

        return response()->json($this->toResponseData($settings));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var Setting|null $settings */
        $settings = Setting::query()->first();

        if ($settings === null) {
            Setting::create($validated);
        } else {
            $filtered = array_filter($validated, fn (mixed $value): bool => $value !== null);
            $settings->update($filtered);
        }

        return response()->json(['message' => 'Settings updated']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toResponseData(?Setting $settings): array
    {
        return [
            'gitlab_username'  => $settings?->gitlab_username,
            'gitlab_email'     => $settings?->gitlab_email,
            'bitrix24_user_id' => $settings?->bitrix24_user_id,
            'llm_provider'     => $settings === null
                ? LlmProvider::Claude->value
                : $settings->llm_provider?->value,
            'llm_system_prompt'       => $settings?->llm_system_prompt,
            'developer_name'          => $settings?->developer_name,
            'developer_position'      => $settings?->developer_position,
            'enriched_prompt_enabled' => $settings === null ? true : $settings->enriched_prompt_enabled,
            'sync_schedule_time'      => $settings === null ? '03:00' : $settings->sync_schedule_time,
            'has_gitlab_token'        => $settings?->gitlab_token !== null,
            'has_bitrix24_api_key'    => $settings?->bitrix24_api_key !== null,
            'has_llm_api_key'         => $settings?->llm_api_key !== null,
        ];
    }
}
