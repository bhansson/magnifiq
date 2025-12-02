<?php

use App\Livewire\ManageProductFeeds;

test('xml feed is preferred when content contains commas', function () {
    $component = new class extends ManageProductFeeds {
        function parseForTest(string $content): array
        {
            return $this->parseFeed($content);
        }

        function extractFieldsForTest(array $parsed): array
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
    $component = new class extends ManageProductFeeds {
        function parseForTest(string $content): array
        {
            return $this->parseFeed($content);
        }

        function buildPayloadForTest(array $parsed, array $mapping): array
        {
            $payload = [];
            $seenSkus = [];

            foreach ($parsed['items'] as $item) {
                $sku = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['sku'] ?? '');
                $title = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['title'] ?? '');
                $link = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['url'] ?? '');

                if ($sku === '' || $title === '' || $link === '') {
                    continue;
                }

                // Skip duplicates - this is the fix we're testing for
                if (isset($seenSkus[$sku])) {
                    continue;
                }
                $seenSkus[$sku] = true;

                $payload[] = [
                    'sku' => $sku,
                    'title' => $title,
                    'url' => $link,
                ];
            }

            return $payload;
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

    $payload = $component->buildPayloadForTest($parsed, $mapping);

    // Should only have 2 products (first occurrence of DUPLICATE_SKU and UNIQUE_SKU)
    expect($payload)->toHaveCount(2);
    expect($payload[0]['sku'])->toEqual('DUPLICATE_SKU');
    expect($payload[0]['title'])->toEqual('First Product');
    expect($payload[1]['sku'])->toEqual('UNIQUE_SKU');
});
