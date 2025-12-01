<?php

namespace Database\Factories;

use App\Models\ProductCatalog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCatalog>
 */
class ProductCatalogFactory extends Factory
{
    protected $model = ProductCatalog::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company().' Catalog',
        ];
    }

    /**
     * Create a catalog with feeds in multiple languages.
     */
    public function withFeeds(array $languages = ['en', 'sv', 'de']): static
    {
        return $this->afterCreating(function (ProductCatalog $catalog) use ($languages) {
            foreach ($languages as $language) {
                \App\Models\ProductFeed::factory()->create([
                    'team_id' => $catalog->team_id,
                    'product_catalog_id' => $catalog->id,
                    'language' => $language,
                    'name' => $catalog->name.' - '.strtoupper($language),
                ]);
            }
        });
    }
}
