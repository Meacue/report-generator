<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Narrative\Enums\LlmProvider;
use App\Domain\Settings\Exceptions\InvalidWebhookUrlException;
use App\Domain\Settings\Services\WebhookUrlParser;
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
            'bitrix24_webhook_url'    => ['nullable', 'string', 'regex:/^https?:\/\/[^\/\s]+(?::\d+)?\/rest\/\d+\/[A-Za-z0-9]+\/?$/'],
            'bitrix24_rest_url'       => ['nullable', 'string', 'url'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bitrix24_webhook_url.regex' => 'Webhook URL must match https://<portal>.bitrix24.ru/rest/<user_id>/<api_key>/',
        ];
    }

    /**
     * Strip the synthetic `bitrix24_webhook_url` from the validated payload so
     * it never reaches `Setting::update()`/`Setting::create()` (the column does
     * not exist on the `settings` table — only the three parsed parts do).
     *
     * @param  array<int, string>|int|string|null  $key
     * @param  mixed  $default
     */
    public function validated($key = null, $default = null): mixed
    {
        /** @var array<string, mixed> $data */
        $data = parent::validated();
        unset($data['bitrix24_webhook_url']);

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key, $default);
    }

    /**
     * Parse the unified Bitrix24 webhook URL (if provided) into the three
     * underlying credential columns before validation rules run.
     *
     * Any parsing failure is swallowed here on purpose: the `regex` rule on
     * `bitrix24_webhook_url` will produce the user-facing 422 response.
     */
    protected function prepareForValidation(): void
    {
        $webhook = $this->input('bitrix24_webhook_url');

        if (! is_string($webhook) || trim($webhook) === '') {
            return;
        }

        try {
            $parsed = app(WebhookUrlParser::class)->parse($webhook);
        } catch (InvalidWebhookUrlException) {
            return;
        }

        $this->merge([
            'bitrix24_rest_url' => $parsed->restUrl,
            'bitrix24_user_id'  => $parsed->userId,
            'bitrix24_api_key'  => $parsed->apiKey,
        ]);
    }
}
