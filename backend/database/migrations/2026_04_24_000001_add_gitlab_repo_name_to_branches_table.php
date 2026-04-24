<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('gitlab_repo_name')->nullable()->after('gitlab_repo_id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('gitlab_repo_name');
        });
    }
};
