<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->text('gitlab_token')->nullable();
            $table->string('gitlab_username')->nullable();
            $table->text('bitrix24_api_key')->nullable();
            $table->string('bitrix24_user_id')->nullable();
            $table->string('llm_provider')->default('claude');
            $table->text('llm_api_key')->nullable();
            $table->text('llm_system_prompt')->nullable();
            $table->string('developer_name')->nullable();
            $table->string('developer_position')->nullable();
            $table->string('sync_schedule_time')->default('03:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
