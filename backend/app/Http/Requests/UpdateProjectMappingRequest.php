<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMappingRequest extends FormRequest
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
            'gitlab_repo_id'        => ['sometimes', 'integer'],
            'gitlab_repo_name'      => ['sometimes', 'string', 'max:255'],
            'bitrix24_project_id'   => ['sometimes', 'integer'],
            'bitrix24_project_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
