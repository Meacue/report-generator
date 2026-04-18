<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('participation_roles')->nullable()->after('project_name');
            $table->boolean('is_external')->default(false)->after('participation_roles');
            $table->index('is_external');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['is_external']);
            $table->dropColumn(['participation_roles', 'is_external']);
        });
    }
};
