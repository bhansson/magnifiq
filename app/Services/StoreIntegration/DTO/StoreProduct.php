<?php

namespace App\Services\StoreIntegration\DTO;

/**
 * Normalized product data from any store platform.
 */
readonly class StoreProduct
{
    public function __construct(
        public string $externalId,
        public string $sku,
        public string $title,
        public ?string $description = null,
        public ?string $brand = null,
        public ?string $url = null,
        public ?string $imageUrl = null,
        public array $additionalImages = [],
        public array $variants = [],
        public ?string $price = null,
        public ?int $inventory = null,
        public ?string $gtin = null,
        public array $metadata = [],
    ) {}
}
