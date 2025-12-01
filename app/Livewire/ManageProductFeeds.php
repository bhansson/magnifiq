<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductCatalog;
use App\Models\ProductFeed;
use App\Models\TeamActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use SimpleXMLElement;

class ManageProductFeeds extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:255')]
    public string $feedName = '';

    #[Validate('nullable|url|max:2048')]
    public string $feedUrl = '';

    #[Validate(ProductFeed::LANGUAGE_VALIDATION_RULE)]
    public string $language = 'en';

    #[Validate('nullable|file|max:5120|mimetypes:text/xml,application/xml,application/rss+xml,text/csv,text/plain,application/octet-stream')]
    public $feedFile;

    public array $availableFields = [];

    public array $mapping = [
        'sku' => '',
        'gtin' => '',
        'title' => '',
        'brand' => '',
        'description' => '',
        'url' => '',
        'image_link' => '',
        'additional_image_link' => '',
    ];

    public bool $showMapping = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public Collection $feeds;

    public Collection $catalogs;

    #[Validate('nullable|integer|exists:product_catalogs,id')]
    public ?int $selectedCatalogId = null;

    #[Validate('nullable|string|max:255')]
    public string $newCatalogName = '';

    public bool $showCreateCatalog = false;

    public ?int $editingCatalogId = null;

    public string $editingCatalogName = '';

    public ?int $movingFeedId = null;

    public ?int $moveToCatalogId = null;

    protected array $lastParsed = [
        'type' => 'xml',
        'items' => null,
        'namespaces' => [],
    ];

    protected ?string $lastContentType = null;

    protected ?string $lastContentSample = null;

    protected array $lastFetchInfo = [];

    protected bool $isRefreshing = false;

    protected array $refreshStatus = [];

    public function mount(): void
    {
        $this->loadCatalogs();
        $this->loadFeeds();
    }

    public function updatedFeedFile(): void
    {
        $this->reset(['statusMessage', 'errorMessage', 'availableFields', 'showMapping']);
    }

    public function loadCatalogs(): void
    {
        $team = $this->currentTeam();

        $this->catalogs = ProductCatalog::query()
            ->where('team_id', $team->id)
            ->withCount('feeds')
            ->with(['feeds' => function ($query) {
                $query->withCount('products');
            }])
            ->orderBy('name')
            ->get();
    }

    public function loadFeeds(): void
    {
        $team = $this->currentTeam();

        $this->feeds = ProductFeed::query()
            ->withCount('products')
            ->with('catalog:id,name')
            ->where('team_id', $team->id)
            ->latest()
            ->get();
    }

    public function refreshFeed(int $feedId): void
    {
        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($feedId);

        if (! $feed->feed_url) {
            $this->errorMessage = 'Cannot refresh uploaded feeds without a URL.';

            return;
        }

        $previousUrl = $this->feedUrl;
        $previousFile = $this->feedFile;

        $this->isRefreshing = true;
        $this->refreshStatus[$feedId] = 'refreshing';
        $this->feedUrl = $feed->feed_url;
        $this->feedFile = null;

        try {
            $content = $this->retrieveFeedContent();
            $parsed = $this->parseFeed($content);

            if ($parsed['items']->isEmpty()) {
                throw new \RuntimeException('No products were found in the supplied feed.');
            }

            $fields = $this->extractFieldsFromSample($parsed);

            if (empty($fields)) {
                throw new \RuntimeException('Could not determine available fields in the feed.');
            }

            $mapping = array_merge([
                'sku' => '',
                'gtin' => '',
                'title' => '',
                'brand' => '',
                'description' => '',
                'url' => '',
                'image_link' => '',
                'additional_image_link' => '',
            ], Arr::only($feed->field_mappings ?? [], [
                'sku',
                'gtin',
                'title',
                'brand',
                'description',
                'url',
                'image_link',
                'additional_image_link',
            ]));

            foreach (['sku', 'title', 'url'] as $required) {
                if (empty($mapping[$required])) {
                    throw new \RuntimeException('Feed is missing a mapping for '.$required.'.');
                }
            }

            DB::transaction(function () use ($feed, $parsed, $mapping): void {
                $feed->forceFill([
                    'field_mappings' => Arr::only($mapping, [
                        'sku',
                        'gtin',
                        'title',
                        'brand',
                        'description',
                        'url',
                        'image_link',
                        'additional_image_link',
                    ]),
                ])->save();

                // Use upsert to preserve existing product IDs and relationships
                $chunks = $parsed['items']->chunk(100);
                $type = $parsed['type'];
                $namespaces = $parsed['namespaces'];
                $seenSkus = [];

                foreach ($chunks as $chunk) {
                    $payload = [];
                    foreach ($chunk as $item) {
                        $sku = $this->extractValue($type, $namespaces, $item, $mapping['sku'] ?? '');
                        $title = $this->extractValue($type, $namespaces, $item, $mapping['title'] ?? '');
                        $link = $this->extractValue($type, $namespaces, $item, $mapping['url'] ?? '');

                        if ($sku === '' || $title === '' || $link === '') {
                            continue;
                        }

                        // Skip duplicate SKUs - keep first occurrence only
                        if (isset($seenSkus[$sku])) {
                            continue;
                        }
                        $seenSkus[$sku] = true;

                        $payload[] = [
                            'product_feed_id' => $feed->id,
                            'team_id' => $feed->team_id,
                            'sku' => $sku,
                            'gtin' => $this->maybeValue($type, $namespaces, $item, 'gtin', $mapping),
                            'title' => $title,
                            'brand' => $this->maybeValue($type, $namespaces, $item, 'brand', $mapping),
                            'description' => $this->maybeValue($type, $namespaces, $item, 'description', $mapping),
                            'url' => $link,
                            'image_link' => $this->maybeValue($type, $namespaces, $item, 'image_link', $mapping),
                            'additional_image_link' => $this->maybeValue($type, $namespaces, $item, 'additional_image_link', $mapping),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($payload)) {
                        // Upsert based on the unique constraint: team_id + sku + product_feed_id
                        Product::upsert(
                            $payload,
                            ['team_id', 'sku', 'product_feed_id'],
                            ['gtin', 'title', 'brand', 'description', 'url', 'image_link', 'additional_image_link', 'updated_at']
                        );
                    }
                }

                $feed->touch();
            });

            $this->statusMessage = 'Feed refreshed successfully.';

            TeamActivity::create([
                'team_id' => $feed->team_id,
                'user_id' => Auth::id(),
                'type' => TeamActivity::TYPE_FEED_REFRESHED,
                'subject_type' => ProductFeed::class,
                'subject_id' => $feed->id,
                'properties' => [
                    'feed_name' => $feed->name,
                ],
            ]);

            $this->loadFeeds();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Unable to refresh feed: '.$e->getMessage();
        } finally {
            $this->feedUrl = $previousUrl;
            $this->feedFile = $previousFile;
            $this->isRefreshing = false;
            $this->refreshStatus[$feedId] = 'idle';
        }
    }

    public function deleteFeed(int $feedId): void
    {
        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->withCount('products')
            ->findOrFail($feedId);

        $feedName = $feed->name;
        $productCount = $feed->products_count;
        $teamId = $feed->team_id;

        DB::transaction(function () use ($feed): void {
            $feed->products()->delete();
            $feed->delete();
        });

        TeamActivity::create([
            'team_id' => $teamId,
            'user_id' => Auth::id(),
            'type' => TeamActivity::TYPE_FEED_DELETED,
            'subject_type' => null,
            'subject_id' => null,
            'properties' => [
                'feed_name' => $feedName,
                'product_count' => $productCount,
            ],
        ]);

        $this->statusMessage = 'Feed deleted successfully.';
        $this->loadFeeds();
    }

    public function fetchFields(): void
    {
        if ($this->isRefreshing) {
            // During automated refresh we skip interactive mapping display.
            return;
        }

        $this->resetMessages();

        if (! $this->feedUrl && ! $this->feedFile) {
            $this->errorMessage = 'Provide a feed URL or upload a feed file.';

            return;
        }

        try {
            $content = $this->retrieveFeedContent();
            logger()->debug('Feed content preview', [
                'sample' => Str::limit($content, 120),
                'starts_with' => Str::of($content)->trim()->substr(0, 5),
                'content_type' => $this->lastContentType,
                'fetch_info' => $this->lastFetchInfo,
            ]);
            $parsed = $this->parseFeed($content);

            if ($parsed['items']->isEmpty()) {
                $this->errorMessage = 'No products were found in the supplied feed.';

                return;
            }

            $fields = $this->extractFieldsFromSample($parsed);

            if (empty($fields)) {
                $this->errorMessage = 'Could not determine available fields in the feed.';

                return;
            }

            $this->availableFields = $fields;
            $this->suggestMappings($fields);
            $this->showMapping = true;
            $this->lastParsed = $parsed;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Unable to read feed: '.$e->getMessage();
            if ($this->lastContentType) {
                $this->errorMessage .= ' (Content-Type: '.$this->lastContentType.')';
            }
            if ($this->lastContentSample) {
                $this->errorMessage .= ' Sample: '.Str::limit(Str::replace('\n', ' ', $this->lastContentSample), 120);
            }
        }
    }

    public function importFeed(): void
    {
        $this->resetMessages();

        if (! $this->isRefreshing) {
            $this->validate();
        }

        if (! $this->feedUrl && ! $this->feedFile) {
            $this->errorMessage = 'Provide a feed URL or upload a feed file.';

            return;
        }

        if (! $this->isRefreshing) {
            foreach (['sku', 'title', 'url'] as $required) {
                if (empty($this->mapping[$required])) {
                    $this->errorMessage = 'Please select a field for '.$required.'.';

                    return;
                }
            }
        }

        $team = $this->currentTeam();

        try {
            $content = $this->retrieveFeedContent();
            $parsed = $this->parseFeed($content);
            $items = $parsed['items'];

            if ($items->isEmpty()) {
                $this->errorMessage = 'No products found to import.';

                return;
            }

            $language = $this->normalizedLanguage();
            $this->language = $language;

            $importedFeed = null;
            $importedCount = 0;

            DB::transaction(function () use ($team, $items, $parsed, $language, &$importedFeed, &$importedCount): void {
                $feed = $this->findOrCreateFeed($team->id, $language);

                $feed->forceFill([
                    'name' => $this->resolveFeedName(),
                    'feed_url' => $this->feedUrl ?: null,
                    'language' => $language,
                    'field_mappings' => $this->mapping,
                ])->save();

                // Use upsert to preserve existing product IDs and relationships
                $chunks = $items->chunk(100);
                $seenSkus = [];

                foreach ($chunks as $chunk) {
                    $payload = [];
                    foreach ($chunk as $item) {
                        $sku = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['sku'] ?? '');
                        $title = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['title'] ?? '');
                        $link = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['url'] ?? '');

                        if ($sku === '' || $title === '' || $link === '') {
                            continue;
                        }

                        // Skip duplicate SKUs - keep first occurrence only
                        if (isset($seenSkus[$sku])) {
                            continue;
                        }
                        $seenSkus[$sku] = true;

                        $payload[] = [
                            'product_feed_id' => $feed->id,
                            'team_id' => $team->id,
                            'sku' => $sku,
                            'gtin' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'gtin'),
                            'title' => $title,
                            'brand' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'brand'),
                            'description' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'description'),
                            'url' => $link,
                            'image_link' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'image_link'),
                            'additional_image_link' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'additional_image_link'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($payload)) {
                        // Upsert based on the unique constraint: team_id + sku + product_feed_id
                        Product::upsert(
                            $payload,
                            ['team_id', 'sku', 'product_feed_id'],
                            ['gtin', 'title', 'brand', 'description', 'url', 'image_link', 'additional_image_link', 'updated_at']
                        );
                        $importedCount += count($payload);
                    }
                }

                $importedFeed = $feed;
            });

            if ($importedFeed) {
                TeamActivity::recordFeedImported($importedFeed, Auth::id(), $importedCount);
            }

            $this->reset(['feedFile']);
            $this->statusMessage = 'Feed imported successfully.';
            $this->loadFeeds();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Failed to import feed: '.$e->getMessage();
        }
    }

    protected function resolveFeedName(): string
    {
        if ($this->feedName) {
            return $this->feedName;
        }

        if ($this->feedUrl) {
            return Str::of($this->feedUrl)->after('//')->before('?')->trim('/')->value() ?: 'Product Feed';
        }

        return 'Product Feed';
    }

    protected function retrieveFeedContent(): string
    {
        if ($this->feedFile) {
            return file_get_contents($this->feedFile->getRealPath());
        }

        $response = Http::withOptions([
            'verify' => false,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
            ],
            'timeout' => 20,
        ])->withHeaders([
            'User-Agent' => 'Magnifiq-FeedFetcher/1.0 (+https://example.com)',
            'Accept' => 'text/xml,application/xml,application/rss+xml,text/csv,application/csv,text/plain;q=0.9,*/*;q=0.8',
        ])->get($this->feedUrl);

        if (! $response->successful()) {
            logger()->warning('Feed request failed', [
                'url' => $this->feedUrl,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Feed responded with status '.$response->status());
        }

        $body = $response->body();
        $this->lastContentType = $response->header('Content-Type');
        $encodingHeader = $response->header('Content-Encoding');
        $charset = $response->header('charset') ?? $response->encoding();

        $this->lastFetchInfo = [
            'url' => $this->feedUrl,
            'status' => $response->status(),
            'content_type' => $this->lastContentType,
            'encoding_header' => $encodingHeader,
            'charset' => $charset,
            'length' => strlen($body),
        ];

        $body = $this->maybeDecodeBody($body, $encodingHeader);

        logger()->debug('Feed HTTP response', [
            'url' => $this->feedUrl,
            'status' => $response->status(),
            'content_type' => $this->lastContentType,
            'encoding_header' => $encodingHeader,
            'charset' => $charset,
            'length' => strlen($body),
        ]);

        if ($charset && Str::lower($charset) !== 'utf-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false) {
                $body = $converted;
            }
        }

        $this->lastContentSample = Str::limit($body, 1000);

        return $body;
    }

    protected function parseFeed(string $content): array
    {
        $trimmed = ltrim($content);

        $looksLikeCsv = $this->isLikelyCsv($trimmed);
        $looksLikeXml = str_starts_with($trimmed, '<');

        if ($looksLikeCsv) {
            $csv = $this->parseCsv($content);
            if ($csv['items']->isNotEmpty()) {
                return $csv;
            }
            logger()->debug('CSV parse returned no items despite heuristic', ['url' => $this->feedUrl]);
        }

        if ($looksLikeXml) {
            $xml = $this->parseXml($content);
            if ($xml['items']->isNotEmpty()) {
                return $xml;
            }
            logger()->debug('XML parse returned no items despite heuristic', ['url' => $this->feedUrl]);
        }

        if (! $looksLikeCsv) {
            $csv = $this->parseCsv($content);
            if ($csv['items']->isNotEmpty()) {
                return $csv;
            }
        }

        if (! $looksLikeXml) {
            $xml = $this->parseXml($content);
            if ($xml['items']->isNotEmpty()) {
                return $xml;
            }
        }

        logger()?->error('Feed parse failed for both CSV and XML', [
            'url' => $this->feedUrl,
            'content_type' => $this->lastContentType,
            'fetch_info' => $this->lastFetchInfo,
        ]);

        throw new \RuntimeException('Feed could not be parsed as XML or CSV.');
    }

    protected function parseXml(string $content): array
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            $errors = collect(libxml_get_errors())->map(fn ($err) => trim($err->message));
            libxml_clear_errors();
            if ($errors->isNotEmpty()) {
                logger()->debug('XML parse errors', ['errors' => $errors->take(5)]);
            }

            return ['type' => 'xml', 'items' => collect(), 'namespaces' => []];
        }

        if (isset($xml->channel->item)) {
            $items = $this->collectXmlItems($xml->channel->item);
        } elseif (isset($xml->entry)) {
            $items = $this->collectXmlItems($xml->entry);
        } else {
            $items = collect();
        }

        return [
            'type' => 'xml',
            'items' => $items,
            'namespaces' => $xml->getNameSpaces(true),
        ];
    }

    protected function collectXmlItems($element): Collection
    {
        if ($element instanceof SimpleXMLElement) {
            $items = [];

            foreach ($element as $child) {
                $items[] = $child;
            }

            if (empty($items)) {
                $items[] = $element;
            }

            return collect($items);
        }

        if (is_iterable($element)) {
            return collect($element);
        }

        return collect();
    }

    protected function parseCsv(string $content): array
    {
        $rows = collect();
        $headers = [];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, str_replace(["\r\n", "\r"], "\n", $content));
        rewind($handle);

        $firstLine = '';
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $firstLine = $line;
            break;
        }

        if ($firstLine === '') {
            fclose($handle);

            return ['type' => 'csv', 'items' => collect(), 'namespaces' => []];
        }

        $delimiters = [',', ';', "\t", '|'];
        $delimiter = ',';
        $maxColumns = 0;
        $headerCandidates = [];

        foreach ($delimiters as $candidate) {
            $fields = str_getcsv($firstLine, $candidate);
            $count = count(array_filter($fields, fn ($value) => $value !== null && trim((string) $value) !== ''));

            if ($count > $maxColumns) {
                $maxColumns = $count;
                $delimiter = $candidate;
                $headerCandidates = $fields;
            }
        }

        if ($maxColumns === 0) {
            fclose($handle);

            return ['type' => 'csv', 'items' => collect(), 'namespaces' => []];
        }

        $headers = array_map(function ($header) {
            $clean = trim((string) $header);

            return ltrim($clean, "\xEF\xBB\xBF");
        }, $headerCandidates);

        $rowCount = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($data, fn ($value) => $value !== null && trim((string) $value) !== ''))) {
                continue;
            }

            if (count($data) !== count($headers)) {
                continue;
            }

            $rows->push(array_combine(
                $headers,
                array_map(fn ($value) => trim((string) $value), $data)
            ));
            $rowCount++;
        }

        fclose($handle);

        logger()->debug('CSV parse summary', [
            'url' => $this->feedUrl,
            'delimiter' => $delimiter,
            'headers' => $headers,
            'row_count' => $rowCount,
        ]);

        return [
            'type' => 'csv',
            'items' => $rows,
            'namespaces' => [],
        ];
    }

    protected function isLikelyCsv(string $trimmed): bool
    {
        if (Str::startsWith($trimmed, '<')) {
            return false;
        }

        if ($this->lastContentType && Str::contains(Str::lower($this->lastContentType), ['csv', 'excel'])) {
            return true;
        }

        if ($this->feedUrl && Str::endsWith(Str::lower($this->feedUrl), ['.csv', '.txt'])) {
            return true;
        }

        if ($trimmed === '') {
            return false;
        }

        return str_contains($trimmed, ',') || str_contains($trimmed, ';') || str_contains($trimmed, "\t") || str_contains($trimmed, '|');
    }

    protected function maybeDecodeBody(string $body, ?string $encodingHeader): string
    {
        if (! $encodingHeader) {
            return $body;
        }

        $encodingHeader = Str::lower($encodingHeader);

        if (str_contains($encodingHeader, 'gzip')) {
            $decoded = @gzdecode($body);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if (str_contains($encodingHeader, 'deflate')) {
            $decoded = @gzuncompress($body);
            if ($decoded === false) {
                $decoded = @gzinflate($body);
            }
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $body;
    }

    protected function extractFieldsFromSample(array $parsed): array
    {
        if ($parsed['items']->isEmpty()) {
            return [];
        }

        $item = $parsed['items']->first();

        if ($parsed['type'] === 'csv') {
            return array_keys($item);
        }

        return $this->extractFieldsFromXmlItem($item, $parsed['namespaces']);
    }

    protected function extractFieldsFromXmlItem(SimpleXMLElement $item, array $namespaces): array
    {
        $fields = [];

        foreach ($item->children() as $child) {
            $name = $child->getName();
            $value = trim((string) $child);

            if ($value !== '') {
                $fields[$name] = true;
            }
        }

        foreach ($namespaces as $prefix => $namespace) {
            foreach ($item->children($namespace) as $child) {
                $name = ($prefix ? "{$prefix}:" : '').$child->getName();
                $value = trim((string) $child);

                if ($value !== '') {
                    $fields[$name] = true;
                }
            }
        }

        return array_keys($fields);
    }

    protected function extractValue(string $type, array $namespaces, $item, string $field): string
    {
        if (! $field) {
            return '';
        }

        if ($type === 'csv') {
            return trim((string) ($item[$field] ?? ''));
        }

        return $this->extractXmlValue($namespaces, $item, $field);
    }

    protected function extractXmlValue(array $namespaces, SimpleXMLElement $item, string $field): string
    {
        $valueNode = null;

        if (str_contains($field, ':')) {
            [$prefix, $name] = explode(':', $field, 2);

            if (isset($namespaces[$prefix])) {
                $children = $item->children($namespaces[$prefix]);
                $valueNode = $children ? $children->{$name} ?? null : null;
            }
        } else {
            $valueNode = $item->{$field} ?? null;
        }

        return $valueNode === null ? '' : trim((string) $valueNode);
    }

    protected function maybeValue(string $type, array $namespaces, $item, string $field, ?array $mappingOverride = null): ?string
    {
        $mapping = $mappingOverride ?? $this->mapping;

        $value = $this->extractValue($type, $namespaces, $item, $mapping[$field] ?? '');

        return $value !== '' ? $value : null;
    }

    protected function findOrCreateFeed(int $teamId, string $language): ProductFeed
    {
        $query = ProductFeed::query()
            ->where('team_id', $teamId)
            ->where('language', $language);

        if ($this->feedUrl) {
            $query->where('feed_url', $this->feedUrl);
        } else {
            $query->whereNull('feed_url')->where('name', $this->resolveFeedName());
        }

        return $query->first() ?? new ProductFeed(['team_id' => $teamId]);
    }

    protected function normalizedLanguage(): string
    {
        $language = Str::lower(trim($this->language));

        if ($language === '') {
            return 'en';
        }

        return array_key_exists($language, ProductFeed::languageOptions())
            ? $language
            : 'en';
    }

    protected function suggestMappings(array $fields): void
    {
        $fieldSet = collect($fields);

        $this->mapping['sku'] = $this->pickField($fieldSet, ['g:id', 'id', 'item_group_id', 'sku']);
        $this->mapping['gtin'] = $this->pickField($fieldSet, ['g:gtin', 'gtin']);
        $this->mapping['title'] = $this->pickField($fieldSet, ['g:title', 'title', 'item_title']);
        $this->mapping['brand'] = $this->pickField($fieldSet, ['g:brand', 'brand', 'g:manufacturer', 'manufacturer']);
        $this->mapping['description'] = $this->pickField($fieldSet, ['g:description', 'description']);
        $this->mapping['url'] = $this->pickField($fieldSet, ['g:link', 'link', 'url']);
        $this->mapping['image_link'] = $this->pickField($fieldSet, ['g:image_link', 'image_link', 'image']);
        $this->mapping['additional_image_link'] = $this->pickField($fieldSet, ['g:additional_image_link', 'additional_image_link']);
    }

    protected function pickField(Collection $fields, array $options): string
    {
        foreach ($options as $option) {
            if ($fields->contains($option)) {
                return $option;
            }
        }

        return '';
    }

    protected function currentTeam()
    {
        $user = Auth::user();

        if (! $user || ! $user->currentTeam) {
            throw new \RuntimeException('A team is required to submit feeds.');
        }

        return $user->currentTeam;
    }

    protected function resetMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Catalog Management
    // ─────────────────────────────────────────────────────────────────────────

    public function createCatalog(): void
    {
        $this->resetMessages();

        $this->validate([
            'newCatalogName' => 'required|string|max:255',
        ]);

        $team = $this->currentTeam();

        $catalog = ProductCatalog::create([
            'team_id' => $team->id,
            'name' => $this->newCatalogName,
        ]);

        TeamActivity::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'type' => TeamActivity::TYPE_CATALOG_CREATED,
            'subject_type' => ProductCatalog::class,
            'subject_id' => $catalog->id,
            'properties' => [
                'catalog_name' => $catalog->name,
            ],
        ]);

        $this->statusMessage = 'Catalog "'.$catalog->name.'" created successfully.';
        $this->newCatalogName = '';
        $this->showCreateCatalog = false;
        $this->loadCatalogs();
    }

    public function startEditCatalog(int $catalogId): void
    {
        $catalog = ProductCatalog::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($catalogId);

        $this->editingCatalogId = $catalog->id;
        $this->editingCatalogName = $catalog->name;
    }

    public function cancelEditCatalog(): void
    {
        $this->editingCatalogId = null;
        $this->editingCatalogName = '';
    }

    public function updateCatalog(): void
    {
        if (! $this->editingCatalogId) {
            return;
        }

        $this->validate([
            'editingCatalogName' => 'required|string|max:255',
        ]);

        $catalog = ProductCatalog::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($this->editingCatalogId);

        $oldName = $catalog->name;
        $catalog->update(['name' => $this->editingCatalogName]);

        $this->statusMessage = 'Catalog renamed from "'.$oldName.'" to "'.$catalog->name.'".';
        $this->editingCatalogId = null;
        $this->editingCatalogName = '';
        $this->loadCatalogs();
    }

    public function deleteCatalog(int $catalogId): void
    {
        $this->resetMessages();

        $catalog = ProductCatalog::query()
            ->where('team_id', $this->currentTeam()->id)
            ->withCount('feeds')
            ->findOrFail($catalogId);

        $catalogName = $catalog->name;
        $feedCount = $catalog->feeds_count;

        // Feeds will be set to standalone (null) due to nullOnDelete constraint
        $catalog->delete();

        TeamActivity::create([
            'team_id' => $this->currentTeam()->id,
            'user_id' => Auth::id(),
            'type' => TeamActivity::TYPE_CATALOG_DELETED,
            'subject_type' => null,
            'subject_id' => null,
            'properties' => [
                'catalog_name' => $catalogName,
                'feed_count' => $feedCount,
            ],
        ]);

        $this->statusMessage = 'Catalog "'.$catalogName.'" deleted. '.$feedCount.' '.Str::plural('feed', $feedCount).' moved to uncategorized.';
        $this->loadCatalogs();
        $this->loadFeeds();
    }

    public function startMoveFeed(int $feedId): void
    {
        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($feedId);

        $this->movingFeedId = $feed->id;
        $this->moveToCatalogId = $feed->product_catalog_id;
    }

    public function cancelMoveFeed(): void
    {
        $this->movingFeedId = null;
        $this->moveToCatalogId = null;
    }

    public function confirmMoveFeed(): void
    {
        if (! $this->movingFeedId) {
            return;
        }

        $this->resetMessages();

        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($this->movingFeedId);

        $oldCatalog = $feed->catalog;
        $newCatalog = null;

        if ($this->moveToCatalogId) {
            $newCatalog = ProductCatalog::query()
                ->where('team_id', $this->currentTeam()->id)
                ->find($this->moveToCatalogId);
        }

        $feed->update(['product_catalog_id' => $newCatalog?->id]);

        $fromName = $oldCatalog?->name ?? 'Uncategorized';
        $toName = $newCatalog?->name ?? 'Uncategorized';

        TeamActivity::create([
            'team_id' => $feed->team_id,
            'user_id' => Auth::id(),
            'type' => TeamActivity::TYPE_FEED_MOVED,
            'subject_type' => ProductFeed::class,
            'subject_id' => $feed->id,
            'properties' => [
                'feed_name' => $feed->name,
                'from_catalog' => $fromName,
                'to_catalog' => $toName,
            ],
        ]);

        $this->statusMessage = 'Feed "'.$feed->name.'" moved from "'.$fromName.'" to "'.$toName.'".';
        $this->movingFeedId = null;
        $this->moveToCatalogId = null;
        $this->loadCatalogs();
        $this->loadFeeds();
    }

    public function toggleCreateCatalog(): void
    {
        $this->showCreateCatalog = ! $this->showCreateCatalog;
        if (! $this->showCreateCatalog) {
            $this->newCatalogName = '';
        }
    }

    public function render()
    {
        return view('livewire.manage-product-feeds', [
            'languageOptions' => ProductFeed::languageOptions(),
        ]);
    }
}
