<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_catalogs', function (Blueprint $table) {
            $table->string('slug')->after('name')->nullable();
            $table->unique(['team_id', 'slug']);
        });

        // Generate slugs for existing catalogs
        $catalogs = DB::table('product_catalogs')->get();
        foreach ($catalogs as $catalog) {
            $baseSlug = Str::slug($catalog->name) ?: 'catalog';
            $slug = $baseSlug;
            $counter = 1;

            // Ensure uniqueness within team
            while (DB::table('product_catalogs')
                ->where('team_id', $catalog->team_id)
                ->where('slug', $slug)
                ->where('id', '!=', $catalog->id)
                ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter++;
            }

            DB::table('product_catalogs')
                ->where('id', $catalog->id)
                ->update(['slug' => $slug]);
        }

        // Now make slug non-nullable
        Schema::table('product_catalogs', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_catalogs', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
