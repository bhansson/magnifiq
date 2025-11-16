<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->foreignId('parent_generation_id')
                ->nullable()
                ->after('product_ai_job_id')
                ->constrained('photo_studio_generations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->dropForeign(['parent_generation_id']);
            $table->dropColumn('parent_generation_id');
        });
    }
};
