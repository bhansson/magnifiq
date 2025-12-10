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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use SimpleXMLElement;

class ManageProductFeeds extends Component
{
    use WithFileUploads;

    private const MAPPING_FIELDS = [
        'sku', 'gtin', 'title', 'brand', 'description',
        'url', 'image_link', 'additional_image_link',
    ];

    #[Validate('required|string|max:255')]
    public string $feedName = '';

    /**
     * Selected catalog option: 'new' to create a new catalog, or an existing catalog ID.
     */
    #[Validate('required|string')]
    public string $catalogOption = 'new';

    public string $feedUrl = '';

    #[Validate(ProductFeed::LANGUAGE_VALIDATION_RULE)]
    public string $language = 'en';

    #[Validate('nullable|file|max:20480|mimetypes:text/xml,application/xml,application/rss+xml,text/csv,text/plain,application/octet-stream')]
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

            $mapping = array_merge(
                $this->getEmptyFieldMapping(),
                Arr::only($feed->field_mappings ?? [], self::MAPPING_FIELDS)
            );

            foreach (['sku', 'title', 'url'] as $required) {
                if (empty($mapping[$required])) {
                    throw new \RuntimeException('Feed is missing a mapping for '.$required.'.');
                }
            }

            DB::transaction(function () use ($feed, $parsed, $mapping): void {
                $feed->forceFill([
                    'field_mappings' => Arr::only($mapping, self::MAPPING_FIELDS),
                ])->save();

                $this->upsertProductsFromParsedFeed($feed, $parsed['items'], $parsed, $mapping);

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

        $this->feedUrl = trim($this->feedUrl);
        $this->resetMessages();
        $this->showMapping = false;

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
            $this->lastParsed = $parsed;

            // Auto-populate feed name from URL domain
            $this->feedName = $this->suggestFeedName();

            // Auto-detect language from feed content
            $this->language = $this->detectLanguageFromFeed($content, $parsed);

            $this->showMapping = true;
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

    /**
     * Suggest a feed name based on URL or file name.
     */
    protected function suggestFeedName(): string
    {
        if ($this->feedUrl) {
            // Extract domain name only
            $parsed = parse_url($this->feedUrl);
            $host = $parsed['host'] ?? '';

            // Remove common prefixes like www.
            return preg_replace('/^www\./', '', $host);
        }

        if ($this->feedFile) {
            $filename = $this->feedFile->getClientOriginalName();

            // Remove extension
            return preg_replace('/\.(xml|csv|txt|rss)$/i', '', $filename);
        }

        return 'Product Feed';
    }

    /**
     * Detect language from feed content.
     * Checks RSS/Atom language tags, Google Merchant fields, and URL patterns.
     */
    protected function detectLanguageFromFeed(string $content, array $parsed): string
    {
        $detectedLanguage = null;

        // For XML feeds, check common language indicators
        if ($parsed['type'] === 'xml' && $parsed['items']->isNotEmpty()) {
            $firstItem = $parsed['items']->first();
            $namespaces = $parsed['namespaces'];

            // Check g:content_language (Google Merchant)
            $contentLang = $this->extractXmlValue($namespaces, $firstItem, 'g:content_language');
            if ($contentLang) {
                $detectedLanguage = Str::lower($contentLang);
            }

            // Check g:target_country and infer language
            if (! $detectedLanguage) {
                $targetCountry = $this->extractXmlValue($namespaces, $firstItem, 'g:target_country');
                if ($targetCountry) {
                    $detectedLanguage = $this->languageFromCountry($targetCountry);
                }
            }
        }

        // Check for <language> tag in RSS (usually at channel level, but check content)
        if (! $detectedLanguage && preg_match('/<language>([a-z]{2}(?:-[a-z]{2})?)<\/language>/i', $content, $matches)) {
            $detectedLanguage = Str::lower($matches[1]);
        }

        // Check URL for language hints
        if (! $detectedLanguage && $this->feedUrl) {
            $detectedLanguage = $this->detectLanguageFromUrl($this->feedUrl);
        }

        // Validate against supported languages
        if ($detectedLanguage && array_key_exists($detectedLanguage, ProductFeed::languageOptions())) {
            return $detectedLanguage;
        }

        // Try to match partial (e.g., 'en-us' -> 'en')
        if ($detectedLanguage) {
            $shortCode = Str::before($detectedLanguage, '-');
            if (array_key_exists($shortCode, ProductFeed::languageOptions())) {
                return $shortCode;
            }
        }

        return 'en'; // Default fallback
    }

    /**
     * Detect language code from URL patterns.
     */
    protected function detectLanguageFromUrl(string $url): ?string
    {
        // Common URL patterns: /sv/, /en-gb/, ?lang=sv, etc.
        $patterns = [
            '/\/([a-z]{2}(?:-[a-z]{2})?)\/feed/i',      // /sv/feed, /en-gb/feed
            '/\/([a-z]{2}(?:-[a-z]{2})?)\//i',          // /sv/, /en-gb/
            '/[?&]lang(?:uage)?=([a-z]{2}(?:-[a-z]{2})?)/i', // ?lang=sv, ?language=en-gb
            '/[?&]locale=([a-z]{2}(?:-[a-z]{2})?)/i',   // ?locale=sv
            '/[-_]([a-z]{2}(?:-[a-z]{2})?)\.xml$/i',    // feed-sv.xml, feed_en-gb.xml
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return Str::lower($matches[1]);
            }
        }

        return null;
    }

    /**
     * Map country code to likely language.
     */
    protected function languageFromCountry(string $country): ?string
    {
        $countryToLanguage = [
            'SE' => 'sv',
            'NO' => 'no',
            'DK' => 'da',
            'FI' => 'fi',
            'DE' => 'de',
            'AT' => 'de',
            'CH' => 'de',
            'FR' => 'fr',
            'ES' => 'es',
            'IT' => 'it',
            'NL' => 'nl',
            'BE' => 'nl',
            'PL' => 'pl',
            'PT' => 'pt',
            'GB' => 'en-gb',
            'UK' => 'en-gb',
            'US' => 'en-us',
            'CZ' => 'cs',
            'HU' => 'hu',
            'RO' => 'ro',
            'BG' => 'bg',
            'SK' => 'sk',
            'SI' => 'sl',
            'EE' => 'et',
            'LV' => 'lv',
            'LT' => 'lt',
        ];

        return $countryToLanguage[Str::upper($country)] ?? null;
    }

    public function importFeed(): void
    {
        $this->feedUrl = trim($this->feedUrl);
        $this->resetMessages();

        if (! $this->isRefreshing) {
            $this->validate([
                'feedName' => 'required|string|max:255',
                'feedUrl' => 'nullable|url|max:2048',
                'catalogOption' => 'required|string',
            ]);
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

        // Validate catalog option (must be 'new' or existing catalog ID)
        $catalogId = null;
        if ($this->catalogOption !== 'new') {
            $catalog = ProductCatalog::query()
                ->where('team_id', $team->id)
                ->where('id', $this->catalogOption)
                ->first();

            if (! $catalog) {
                $this->errorMessage = 'Selected catalog not found.';

                return;
            }
            $catalogId = $catalog->id;
        }

        // Check for duplicate feed URL with same language (only for new URL-based imports)
        if (! $this->isRefreshing && $this->feedUrl) {
            $existingFeed = ProductFeed::query()
                ->where('team_id', $team->id)
                ->where('feed_url', $this->feedUrl)
                ->where('language', $this->language)
                ->first();

            if ($existingFeed) {
                $this->errorMessage = 'A feed with this URL and language already exists: "'.$existingFeed->name.'". Use the refresh option to update it instead.';

                return;
            }
        }

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
            $createdCatalog = null;

            DB::transaction(function () use ($team, $items, $parsed, $language, $catalogId, &$importedFeed, &$importedCount, &$createdCatalog): void {
                // Create new catalog if needed, or use existing one with same name
                if ($this->catalogOption === 'new') {
                    $existingCatalog = ProductCatalog::query()
                        ->where('team_id', $team->id)
                        ->where('name', $this->feedName)
                        ->first();

                    if ($existingCatalog) {
                        $catalogId = $existingCatalog->id;
                    } else {
                        $createdCatalog = ProductCatalog::create([
                            'team_id' => $team->id,
                            'name' => $this->feedName,
                        ]);
                        $catalogId = $createdCatalog->id;
                    }
                }

                $feed = $this->findOrCreateFeed($team->id, $language);

                $feed->forceFill([
                    'name' => $this->feedName,
                    'feed_url' => $this->feedUrl ?: null,
                    'language' => $language,
                    'field_mappings' => $this->mapping,
                    'product_catalog_id' => $catalogId,
                ])->save();

                $importedCount = $this->upsertProductsFromParsedFeed($feed, $items, $parsed, $this->mapping);

                $importedFeed = $feed;
            });

            if ($createdCatalog) {
                TeamActivity::create([
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'type' => TeamActivity::TYPE_CATALOG_CREATED,
                    'subject_type' => ProductCatalog::class,
                    'subject_id' => $createdCatalog->id,
                    'properties' => [
                        'catalog_name' => $createdCatalog->name,
                    ],
                ]);
            }

            if ($importedFeed) {
                TeamActivity::recordFeedImported($importedFeed, Auth::id(), $importedCount);
            }

            // Reset form to initial state for next import
            $this->reset([
                'feedUrl',
                'feedFile',
                'feedName',
                'language',
                'availableFields',
                'mapping',
                'showMapping',
            ]);
            $this->language = 'en';
            $this->catalogOption = 'new';
            $this->statusMessage = 'Feed imported successfully.';
            $this->loadCatalogs();
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
        if ($this->feedFile instanceof TemporaryUploadedFile) {
            return $this->feedFile->get();
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

    /**
     * Upsert products from a parsed feed into the database.
     *
     * Processes items in chunks of 100, deduplicates by SKU (keeping first occurrence),
     * and uses database upsert to preserve existing product IDs and relationships.
     *
     * @param  ProductFeed  $feed  The feed to associate products with
     * @param  Collection  $items  Parsed feed items (XML elements or CSV rows)
     * @param  array  $parsed  Parsed feed metadata with 'type' and 'namespaces' keys
     * @param  array  $mapping  Field mapping array (sku, title, url, etc.)
     * @return int Number of products upserted
     */
    protected function upsertProductsFromParsedFeed(ProductFeed $feed, Collection $items, array $parsed, array $mapping): int
    {
        $chunks = $items->chunk(100);
        $type = $parsed['type'];
        $namespaces = $parsed['namespaces'];
        $seenSkus = [];
        $upsertedCount = 0;

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
                $upsertedCount += count($payload);
            }
        }

        return $upsertedCount;
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

    private function getEmptyFieldMapping(): array
    {
        return array_fill_keys(self::MAPPING_FIELDS, '');
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
