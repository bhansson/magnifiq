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
        Schema::create('store_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->default('pending'); // pending, processing, completed, failed
            $table->integer('products_synced')->default(0);
            $table->integer('products_created')->default(0);
            $table->integer('products_updated')->default(0);
            $table->integer('products_deleted')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_connection_id', 'status']);
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_sync_jobs');
    }
};
