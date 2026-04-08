<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assignments'             => ['required', 'array', 'min:1'],
            'assignments.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'assignments.*.task_id'   => ['required', 'integer', Rule::exists('tasks', 'bitrix24_task_id')->whereNull('deleted_at')],
        ];
    }
}
