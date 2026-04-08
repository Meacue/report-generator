<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('confidence_level');
            $table->string('resolved_by')->default('system');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
