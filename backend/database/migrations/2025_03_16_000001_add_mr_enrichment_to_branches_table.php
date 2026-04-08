<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('mr_title')->nullable()->after('mr_web_url');
            $table->text('mr_description')->nullable()->after('mr_title');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['mr_title', 'mr_description']);
        });
    }
};
