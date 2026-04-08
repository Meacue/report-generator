<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'title'     => ['required', 'string', 'max:500'],
        ];
    }
}
