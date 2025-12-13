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
            // Prevent duplicate feeds for same store connection + language combination.
            // Allows multiple feeds with same language if store_connection_id is null (URL feeds).
            $table->unique(
                ['store_connection_id', 'language'],
                'product_feeds_store_connection_language_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_feeds', function (Blueprint $table) {
            $table->dropUnique('product_feeds_store_connection_language_unique');
        });
    }
};
