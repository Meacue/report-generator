<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bitrix24_task_id')->unique();
            $table->string('title');
            $table->string('status');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('project_name')->nullable();
            $table->string('bitrix24_url')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
