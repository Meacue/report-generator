<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMappingRequest extends FormRequest
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
            'gitlab_repo_id'        => ['required', 'integer'],
            'gitlab_repo_name'      => ['required', 'string', 'max:255'],
            'bitrix24_project_id'   => ['required', 'integer'],
            'bitrix24_project_name' => ['required', 'string', 'max:255'],
        ];
    }
}
