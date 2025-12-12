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
        Schema::table('product_ai_generations', function (Blueprint $table) {
            $table->timestamp('unpublished_at')->nullable()->after('meta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_ai_generations', function (Blueprint $table) {
            $table->dropColumn('unpublished_at');
        });
    }
};
