<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('project_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gitlab_repo_id');
            $table->string('gitlab_repo_name');
            $table->unsignedBigInteger('bitrix24_project_id');
            $table->string('bitrix24_project_name');
            $table->timestamps();
            $table->unique(['gitlab_repo_id', 'bitrix24_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_mappings');
    }
};
