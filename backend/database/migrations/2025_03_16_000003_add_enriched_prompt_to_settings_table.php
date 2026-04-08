<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('enriched_prompt_enabled')->default(true)->after('llm_system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('enriched_prompt_enabled');
        });
    }
};
