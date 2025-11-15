<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('type', 20)->default('customer')->after('personal_team');
            $table->foreignId('parent_team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['type', 'parent_team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['parent_team_id']);
            $table->dropColumn(['type', 'parent_team_id']);
        });
    }
};
