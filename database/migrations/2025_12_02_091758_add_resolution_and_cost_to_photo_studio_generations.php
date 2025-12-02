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
            $table->string('resolution', 32)->nullable()->after('model');
            $table->decimal('estimated_cost', 8, 4)->nullable()->after('resolution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'estimated_cost']);
        });
    }
};
