<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('team_id')
                ->constrained('photo_studio_generations')
                ->onDelete('cascade');

            $table->text('edit_instruction')->nullable()->after('prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'edit_instruction']);
        });
    }
};
