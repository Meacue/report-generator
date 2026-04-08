<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('gitlab_mr_iid')->nullable();
            $table->string('mr_state')->nullable();
            $table->string('mr_target_branch')->nullable();
            $table->string('mr_web_url')->nullable();
            $table->timestamp('mr_merged_at')->nullable();

            $table->index(['gitlab_repo_id', 'gitlab_mr_iid']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['gitlab_repo_id', 'gitlab_mr_iid']);

            $table->dropColumn([
                'gitlab_mr_iid',
                'mr_state',
                'mr_target_branch',
                'mr_web_url',
                'mr_merged_at',
            ]);
        });
    }
};
