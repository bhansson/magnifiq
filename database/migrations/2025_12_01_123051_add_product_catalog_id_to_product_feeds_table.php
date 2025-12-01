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
        Schema::table('product_feeds', function (Blueprint $table) {
            $table->foreignId('product_catalog_id')
                ->nullable()
                ->after('team_id')
                ->constrained('product_catalogs')
                ->nullOnDelete();

            $table->index('product_catalog_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_feeds', function (Blueprint $table) {
            $table->dropForeign(['product_catalog_id']);
            $table->dropIndex(['product_catalog_id']);
            $table->dropColumn('product_catalog_id');
        });
    }
};
