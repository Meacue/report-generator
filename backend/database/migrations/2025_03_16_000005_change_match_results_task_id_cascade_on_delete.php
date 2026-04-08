<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // SQLite does not support ALTER FOREIGN KEY, so we recreate the table.
        // commits.branch_id and match_results.branch_id already have ON DELETE CASCADE.
        // Only match_results.task_id needs to change from SET NULL to CASCADE.

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function (): void {
            DB::statement('
                CREATE TABLE "match_results_new" (
                    "id" integer primary key autoincrement not null,
                    "branch_id" integer not null,
                    "task_id" integer,
                    "confidence_level" varchar not null,
                    "resolved_by" varchar not null default \'system\',
                    "resolved_at" datetime,
                    "created_at" datetime,
                    "updated_at" datetime,
                    "deleted_at" datetime,
                    foreign key("branch_id") references "branches"("id") on delete cascade,
                    foreign key("task_id") references "tasks"("id") on delete cascade
                )
            ');

            DB::statement('
                INSERT INTO "match_results_new"
                    ("id", "branch_id", "task_id", "confidence_level", "resolved_by",
                     "resolved_at", "created_at", "updated_at", "deleted_at")
                SELECT
                    "id", "branch_id", "task_id", "confidence_level", "resolved_by",
                    "resolved_at", "created_at", "updated_at", "deleted_at"
                FROM "match_results"
            ');

            DB::statement('DROP TABLE "match_results"');

            DB::statement('ALTER TABLE "match_results_new" RENAME TO "match_results"');

            DB::statement('
                CREATE UNIQUE INDEX "match_results_branch_id_task_id_unique"
                ON "match_results" ("branch_id", "task_id")
            ');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function (): void {
            DB::statement('
                CREATE TABLE "match_results_old" (
                    "id" integer primary key autoincrement not null,
                    "branch_id" integer not null,
                    "task_id" integer,
                    "confidence_level" varchar not null,
                    "resolved_by" varchar not null default \'system\',
                    "resolved_at" datetime,
                    "created_at" datetime,
                    "updated_at" datetime,
                    "deleted_at" datetime,
                    foreign key("branch_id") references "branches"("id") on delete cascade,
                    foreign key("task_id") references "tasks"("id") on delete set null
                )
            ');

            DB::statement('
                INSERT INTO "match_results_old"
                    ("id", "branch_id", "task_id", "confidence_level", "resolved_by",
                     "resolved_at", "created_at", "updated_at", "deleted_at")
                SELECT
                    "id", "branch_id", "task_id", "confidence_level", "resolved_by",
                    "resolved_at", "created_at", "updated_at", "deleted_at"
                FROM "match_results"
            ');

            DB::statement('DROP TABLE "match_results"');

            DB::statement('ALTER TABLE "match_results_old" RENAME TO "match_results"');

            DB::statement('
                CREATE UNIQUE INDEX "match_results_branch_id_task_id_unique"
                ON "match_results" ("branch_id", "task_id")
            ');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
