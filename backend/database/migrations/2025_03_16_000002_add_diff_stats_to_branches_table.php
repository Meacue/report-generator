<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('mr_additions')->nullable()->after('mr_description');
            $table->unsignedInteger('mr_deletions')->nullable()->after('mr_additions');
            $table->json('mr_changed_files')->nullable()->after('mr_deletions');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['mr_additions', 'mr_deletions', 'mr_changed_files']);
        });
    }
};
