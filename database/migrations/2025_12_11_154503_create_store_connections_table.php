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
        Schema::create('store_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_feed_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 50); // 'shopify', 'woocommerce', 'bigcommerce'
            $table->string('name'); // User-friendly name: "Main Store", "US Store"
            $table->string('store_identifier'); // shopify: 'mystore.myshopify.com'
            $table->text('access_token')->nullable(); // Will be encrypted via model cast
            $table->text('refresh_token')->nullable(); // For platforms that use refresh tokens
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable(); // OAuth scopes granted
            $table->string('status', 50)->default('pending'); // pending, connected, error, disconnected
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_settings')->nullable(); // { "interval_minutes": 60, "sync_inventory": true }
            $table->json('metadata')->nullable(); // Platform-specific data
            $table->timestamps();

            $table->unique(['team_id', 'platform', 'store_identifier']);
            $table->index(['team_id', 'platform']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_connections');
    }
};
