<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gitlab_repo_id');
            $table->string('branch_name');
            $table->string('parsed_task_number')->nullable();
            $table->date('parsed_date')->nullable();
            $table->string('parsed_parent_branch')->nullable();
            $table->string('parsed_info')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['gitlab_repo_id', 'branch_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
