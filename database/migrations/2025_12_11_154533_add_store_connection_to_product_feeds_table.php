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
            $table->foreignId('store_connection_id')
                ->nullable()
                ->after('product_catalog_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('source_type', 50)
                ->default('url')
                ->after('store_connection_id'); // 'url', 'upload', 'store_connection'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_feeds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_connection_id');
            $table->dropColumn('source_type');
        });
    }
};
