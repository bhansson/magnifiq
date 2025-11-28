<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table): void {
            $table->string('composition_mode', 32)->nullable()->after('source_reference');
            $table->json('source_references')->nullable()->after('composition_mode');

            $table->index(['team_id', 'composition_mode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'composition_mode', 'created_at']);
            $table->dropColumn(['composition_mode', 'source_references']);
        });
    }
};
