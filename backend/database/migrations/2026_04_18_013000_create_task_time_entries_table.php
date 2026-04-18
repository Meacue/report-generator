<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Idempotency key for upsert from Bitrix24.
            $table->unsignedBigInteger('bitrix24_entry_id')->unique();
            // Business join key to tasks.bitrix24_task_id (no FK: task may arrive later).
            $table->unsignedBigInteger('bitrix24_task_id')->index();
            $table->string('bitrix24_user_id', 64)->index();
            // Tracked duration in seconds (unsignedInteger covers ~136 years).
            $table->unsignedInteger('seconds');
            $table->text('comment')->nullable();
            // Primary field for period filtering (UTC).
            $table->dateTime('tracked_at')->index();
            // Audit field: CREATED_DATE from Bitrix24.
            $table->dateTime('source_created_at')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_entries');
    }
};
