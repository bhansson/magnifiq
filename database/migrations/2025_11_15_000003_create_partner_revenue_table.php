<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_revenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('customer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('customer_revenue_cents'); // Customer's total revenue
            $table->decimal('partner_share_percent', 5, 2); // e.g., 20.00%
            $table->unsignedBigInteger('partner_revenue_cents'); // Partner's share
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['partner_team_id', 'period_start', 'period_end']);
            $table->index('customer_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_revenue');
    }
};
