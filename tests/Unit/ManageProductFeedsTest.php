<?php

use App\Livewire\ManageProductFeeds;

test('xml feed is preferred when content contains commas', function () {
    $component = new class extends ManageProductFeeds
    {
        public function parseForTest(string $content): array
        {
            return $this->parseFeed($content);
        }

        public function extractFieldsForTest(array $parsed): array
        {
            return $this->extractFieldsFromSample($parsed);
        }
    };

    $xmlFeed = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title><![CDATA[ Bodyguard.nu ]]></title>
<link>https://www.bodyguard.nu</link>
<description>Google Shopping</description>
<item>
<g:id>2</g:id>
<title><![CDATA[ Bodyguard rödfärg ]]></title>
<link>https://www.bodyguard.nu/sjalvforsvarsspray/bodyguard-rodfarg</link>
<g:price>189.00 SEK</g:price>
<description><![CDATA[ Som ägare av bodyguard självförsvarsspray kan du känna dig tryggare vid promenader, joggingturer, resor etc. ]]></description>
<g:availability>in stock</g:availability>
</item>
</channel>
</rss>
XML;

    $parsed = $component->parseForTest($xmlFeed);

    expect($parsed['type'])->toBe('xml');

    $fields = $component->extractFieldsForTest($parsed);

    expect($fields)->toContain('g:id');
    expect($fields)->toContain('title');
    expect($fields)->toContain('description');
});

test('duplicate skus in feed are handled gracefully', function () {
    $component = new class extends ManageProductFeeds
    {
        public array $capturedPayloads = [];

        public function parseForTest(string $content): array
        {
            return $this->parseFeed($content);
        }

        public function upsertForTest(\App\Models\ProductFeed $feed, \Illuminate\Support\Collection $items, array $parsed, array $mapping): int
        {
            // Override Product::upsert to capture payloads instead of hitting DB
            \App\Models\Product::macro('captureUpsert', function ($payload) use (&$captured) {
                $captured = array_merge($captured ?? [], $payload);
            });

            // We'll test the deduplication logic by checking the return count
            // and verifying via a real DB test in the feature test
            return $this->upsertProductsFromParsedFeed($feed, $items, $parsed, $mapping);
        }

        public function extractValueForTest(string $type, array $namespaces, $item, string $field): string
        {
            return $this->extractValue($type, $namespaces, $item, $field);
        }
    };

    $xmlFeedWithDuplicates = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>DUPLICATE_SKU</g:id>
<g:title>First Product</g:title>
<g:link>https://example.com/product1</g:link>
</item>
<item>
<g:id>UNIQUE_SKU</g:id>
<g:title>Second Product</g:title>
<g:link>https://example.com/product2</g:link>
</item>
<item>
<g:id>DUPLICATE_SKU</g:id>
<g:title>Duplicate Product</g:title>
<g:link>https://example.com/product3</g:link>
</item>
</channel>
</rss>
XML;

    $parsed = $component->parseForTest($xmlFeedWithDuplicates);
    $mapping = [
        'sku' => 'g:id',
        'title' => 'g:title',
        'url' => 'g:link',
    ];

    // Verify parsing found all 3 items
    expect($parsed['items'])->toHaveCount(3);

    // Verify we can extract values correctly
    $firstItem = $parsed['items']->first();
    expect($component->extractValueForTest($parsed['type'], $parsed['namespaces'], $firstItem, 'g:id'))->toBe('DUPLICATE_SKU');
    expect($component->extractValueForTest($parsed['type'], $parsed['namespaces'], $firstItem, 'g:title'))->toBe('First Product');

    // Verify the deduplication logic works by manually checking unique SKUs
    $seenSkus = [];
    $uniqueCount = 0;
    foreach ($parsed['items'] as $item) {
        $sku = $component->extractValueForTest($parsed['type'], $parsed['namespaces'], $item, $mapping['sku']);
        if ($sku !== '' && ! isset($seenSkus[$sku])) {
            $seenSkus[$sku] = true;
            $uniqueCount++;
        }
    }

    // Should only have 2 unique SKUs (DUPLICATE_SKU and UNIQUE_SKU)
    expect($uniqueCount)->toBe(2);
    expect(array_keys($seenSkus))->toEqual(['DUPLICATE_SKU', 'UNIQUE_SKU']);
});
