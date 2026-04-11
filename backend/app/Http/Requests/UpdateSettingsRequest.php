<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Narrative\Enums\LlmProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, In|string>>
     */
    public function rules(): array
    {
        return [
            'gitlab_token'            => ['nullable', 'string'],
            'gitlab_username'         => ['nullable', 'string', 'max:255'],
            'gitlab_email'            => ['nullable', 'string', 'email', 'max:255'],
            'bitrix24_api_key'        => ['nullable', 'string'],
            'bitrix24_user_id'        => ['nullable', 'string', 'max:255'],
            'llm_provider'            => ['nullable', Rule::in(array_column(LlmProvider::cases(), 'value'))],
            'llm_api_key'             => ['nullable', 'string'],
            'llm_system_prompt'       => ['nullable', 'string'],
            'enriched_prompt_enabled' => ['nullable', 'boolean'],
            'developer_name'          => ['nullable', 'string', 'max:255'],
            'developer_position'      => ['nullable', 'string', 'max:255'],
            'sync_schedule_time'      => ['nullable', 'string', 'max:5'],
        ];
    }
}
