<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Narrative\Enums\LlmProvider;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $gitlab_token
 * @property string|null $gitlab_username
 * @property string|null $gitlab_email
 * @property string|null $bitrix24_api_key
 * @property int|null $bitrix24_user_id
 * @property LlmProvider|null $llm_provider
 * @property string|null $llm_api_key
 * @property string|null $llm_system_prompt
 * @property bool $enriched_prompt_enabled
 * @property string|null $developer_name
 * @property string|null $developer_position
 * @property string|null $sync_schedule_time
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'gitlab_token',
        'gitlab_username',
        'gitlab_email',
        'bitrix24_api_key',
        'bitrix24_user_id',
        'llm_provider',
        'llm_api_key',
        'llm_system_prompt',
        'enriched_prompt_enabled',
        'developer_name',
        'developer_position',
        'sync_schedule_time',
    ];

    protected function casts(): array
    {
        return [
            'gitlab_token'            => 'encrypted',
            'bitrix24_api_key'        => 'encrypted',
            'llm_api_key'             => 'encrypted',
            'llm_provider'            => LlmProvider::class,
            'enriched_prompt_enabled' => 'boolean',
        ];
    }
}
