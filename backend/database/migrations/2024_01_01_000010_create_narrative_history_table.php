<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('narrative_history', function (Blueprint $table) {
            $table->id();
            $table->morphs('narratable');
            $table->text('previous_narrative');
            $table->timestamp('changed_at');
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narrative_history');
    }
};
