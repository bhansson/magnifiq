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
            $table->timestamp('pushed_to_store_at')->nullable()->after('product_ai_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_studio_generations', function (Blueprint $table) {
            $table->dropColumn('pushed_to_store_at');
        });
    }
};
