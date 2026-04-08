<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('report_day_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_task_id')->constrained()->cascadeOnDelete();
            $table->text('narrative')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->unique(['report_day_id', 'report_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_day_tasks');
    }
};
