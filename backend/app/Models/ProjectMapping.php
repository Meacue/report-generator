<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMapping extends Model
{
    /** @use HasFactory<ProjectMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'gitlab_repo_id',
        'gitlab_repo_name',
        'bitrix24_project_id',
        'bitrix24_project_name',
    ];

    protected function casts(): array
    {
        return [
            'gitlab_repo_id'      => 'integer',
            'bitrix24_project_id' => 'integer',
        ];
    }
}
