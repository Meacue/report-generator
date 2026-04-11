<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Narrative\Enums\LlmProvider;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var Setting|null $settings */
        $settings = Setting::query()->first();

        if ($settings === null) {
            return response()->json([
                'gitlab_username'         => null,
                'gitlab_email'            => null,
                'bitrix24_user_id'        => null,
                'llm_provider'            => LlmProvider::Claude->value,
                'llm_system_prompt'       => null,
                'developer_name'          => null,
                'developer_position'      => null,
                'enriched_prompt_enabled' => true,
                'sync_schedule_time'      => '03:00',
                'has_gitlab_token'        => false,
                'has_bitrix24_api_key'    => false,
                'has_llm_api_key'         => false,
            ]);
        }

        return response()->json([
            'gitlab_username'         => $settings->gitlab_username,
            'gitlab_email'            => $settings->gitlab_email,
            'bitrix24_user_id'        => $settings->bitrix24_user_id,
            'llm_provider'            => $settings->llm_provider?->value,
            'llm_system_prompt'       => $settings->llm_system_prompt,
            'enriched_prompt_enabled' => $settings->enriched_prompt_enabled,
            'developer_name'          => $settings->developer_name,
            'developer_position'      => $settings->developer_position,
            'sync_schedule_time'      => $settings->sync_schedule_time,
            'has_gitlab_token'        => $settings->gitlab_token !== null,
            'has_bitrix24_api_key'    => $settings->bitrix24_api_key !== null,
            'has_llm_api_key'         => $settings->llm_api_key !== null,
        ]);
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
}
