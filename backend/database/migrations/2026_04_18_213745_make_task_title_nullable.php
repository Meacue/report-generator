<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make tasks.title nullable to accommodate stub rows.
 *
 * Stub tasks are created when Bitrix24 returns 403 (ACCESS_DENIED) or 404
 * (TASK_NOT_FOUND) for a task referenced by a time-tracking entry. These
 * stubs carry title=null and are rendered with an "Untitled (#ID)"
 * placeholder in the report.
 *
 * NOTE on rollback: the down() migration restores the NOT NULL constraint.
 * Any stub rows that were inserted with title=null will violate this
 * constraint on rollback and must be removed or patched manually beforehand.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
