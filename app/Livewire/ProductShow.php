<?php

namespace App\Livewire;

use App\Jobs\RunProductAiTemplateJob;
use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\ProductFeed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProductShow extends Component
{
    public int $productId;

    public ?string $catalogSlug = null;

    public ?string $currentLanguage = null;

    public array $generationStatus = [];

    public array $generationError = [];

    public array $generationLoading = [];

    public array $generationContent = [];

    protected ?Collection $templateCache = null;

    public function mount(int $productId, ?string $catalogSlug = null, ?string $currentLanguage = null): void
    {
        $this->productId = $productId;
        $this->catalogSlug = $catalogSlug;
        $this->currentLanguage = $currentLanguage;
    }

    public function render()
    {
        $product = $this->product;
        $templates = $this->templates();
        $templatePayload = $this->buildTemplatePayload($product, $templates);
        $languageVersions = $this->languageVersions($product);

        return view('livewire.product-show', [
            'product' => $product,
            'templates' => $templates,
            'templatePayload' => $templatePayload,
            'generationStatus' => $this->generationStatus,
            'generationError' => $this->generationError,
            'generationLoading' => $this->generationLoading,
            'generationContent' => $this->generationContent,
            'languageLabels' => ProductFeed::languageOptions(),
            'languageVersions' => $languageVersions,
            'catalogSlug' => $this->catalogSlug,
        ]);
    }

    public function queueGeneration(int $templateId): void
    {
        $product = $this->product;
        $template = $this->findTemplate($templateId);
        $key = $template->slug;

        $this->generationError[$key] = null;
        $this->generationStatus[$key] = null;
        $this->generationLoading[$key] = true;

        try {
            if (! config('laravel-openrouter.api_key')) {
                throw new \RuntimeException('AI provider API key is not configured.');
            }

            if (! $product->sku) {
                throw new \RuntimeException('Product is missing an SKU, cannot queue generation.');
            }

            $team = Auth::user()->currentTeam;

            if (! $team) {
                throw new \RuntimeException('User is missing an active team.');
            }

            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'sku' => $product->sku,
                'product_ai_template_id' => $template->id,
                'job_type' => ProductAiJob::TYPE_TEMPLATE,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
            ]);

            RunProductAiTemplateJob::dispatch($jobRecord->id);

            if ($template->slug === ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY) {
                $this->generationContent[$key] = null;
            }

            $this->generationStatus[$key] = 'Queued '.$template->name.'. Track progress on the AI Jobs page.';
        } catch (\Throwable $e) {
            $this->generationError[$key] = $e->getMessage();
        } finally {
            $this->generationLoading[$key] = false;
        }
    }

    public function promoteGeneration(int $generationId): void
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        /** @var ProductAiGeneration|null $generation */
        $generation = ProductAiGeneration::query()
            ->with('template')
            ->where('id', $generationId)
            ->where('product_id', $this->productId)
            ->where('team_id', $team->id)
            ->first();

        if (! $generation || ! $generation->template) {
            $templateSlug = 'unknown';
            $this->generationError[$templateSlug] = 'Unable to find requested generation.';

            return;
        }

        $key = $generation->template->slug;

        $this->generationError[$key] = null;
        $this->generationStatus[$key] = null;
        $this->generationLoading[$key] = true;

        try {
            $generation->forceFill([
                'updated_at' => now(),
            ])->save();

            if ($generation->template->slug === ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY) {
                $this->generationContent[$key] = $generation->content;
            }

            $this->generationStatus[$key] = 'Published '.$generation->template->name.' to latest.';

            $this->dispatch('$refresh')->self();
        } catch (\Throwable $e) {
            $this->generationError[$key] = $e->getMessage();
        } finally {
            $this->generationLoading[$key] = false;
        }
    }

    public function getProductProperty(): Product
    {
        $team = Auth::user()->currentTeam;

        abort_if(! $team, 404);

        $templates = $this->templates();
        $historyLimit = $this->historyLimit();
        $templateIds = $templates->pluck('id');
        $multiplier = max($templateIds->count(), 1);

        return Product::query()
            ->with([
                'feed:id,name,language,product_catalog_id',
                'feed.catalog:id,name',
                'aiGenerations' => static function ($query) use ($templateIds, $historyLimit, $multiplier) {
                    $query->with('template')
                        ->whereIn('product_ai_template_id', $templateIds)
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id')
                        ->limit($historyLimit * $multiplier * 2);
                },
            ])
            ->where('team_id', $team->id)
            ->findOrFail($this->productId);
    }

    protected function templates(): Collection
    {
        if ($this->templateCache !== null) {
            return $this->templateCache;
        }

        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        ProductAiTemplate::syncDefaultTemplates();

        $this->templateCache = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->templateCache;
    }

    protected function findTemplate(int $templateId): ProductAiTemplate
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        ProductAiTemplate::syncDefaultTemplates();

        $template = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->active()
            ->find($templateId);

        if (! $template) {
            throw new \RuntimeException('Unable to find requested template.');
        }

        return $template;
    }

    protected function historyLimit(): int
    {
        $limit = (int) config('product-ai.defaults.history_limit', 10);

        return $limit > 0 ? $limit : 10;
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     template: ProductAiTemplate,
     *     latest: ?ProductAiGeneration,
     *     history: \Illuminate\Support\Collection<int, ProductAiGeneration>,
     *     has_content: bool
     * }>
     */
    protected function buildTemplatePayload(Product $product, Collection $templates): array
    {
        $grouped = $product->aiGenerations
            ? $product->aiGenerations->groupBy('product_ai_template_id')
            : collect();

        $payload = [];

        foreach ($templates as $template) {
            $entries = $grouped->get($template->id, collect())
                ->sortByDesc('updated_at')
                ->values();

            /** @var ProductAiGeneration|null $latest */
            $latest = $entries->first();
            $historyLimit = max($template->historyLimit(), 1);
            $history = $entries->slice(1)->take($historyLimit - 1)->values();

            $key = $template->slug;

            if ($latest) {
                $this->generationContent[$key] = $latest->content;
            }

            $payload[] = [
                'key' => $key,
                'template' => $template,
                'latest' => $latest,
                'history' => $history,
                'has_content' => $this->generationHasContent($latest),
            ];
        }

        return $payload;
    }

    protected function generationHasContent(?ProductAiGeneration $generation): bool
    {
        if (! $generation) {
            return false;
        }

        $content = $generation->content;

        if (is_array($content)) {
            return collect($content)
                ->filter(function ($item) {
                    if (is_array($item)) {
                        return collect($item)->filter()->isNotEmpty();
                    }

                    return trim((string) $item) !== '';
                })
                ->isNotEmpty();
        }

        return trim((string) $content) !== '';
    }

    /**
     * Get all language versions of the product for tab navigation.
     *
     * @return Collection<int, array{id: int, language: string, is_current: bool, url: string}>
     */
    protected function languageVersions(Product $product): Collection
    {
        if (! $product->isInCatalog()) {
            return collect();
        }

        return $product->allLanguageVersions()
            ->filter(fn (Product $p) => $p->feed?->language)
            ->sortBy(fn (Product $p) => $p->feed->language)
            ->map(function (Product $p) use ($product) {
                $language = $p->feed->language;
                $isCurrent = $p->id === $product->id;

                // Build URL using semantic routing if catalog slug is available
                if ($this->catalogSlug && $p->sku) {
                    $params = [
                        'catalog' => $this->catalogSlug,
                        'sku' => $p->sku,
                    ];

                    if ($language) {
                        $params['lang'] = $language;
                    }

                    $url = route('products.show', $params);
                } else {
                    // Fallback to legacy route
                    $url = route('products.show.legacy', ['product' => $p->id]);
                }

                return [
                    'id' => $p->id,
                    'language' => $language,
                    'is_current' => $isCurrent,
                    'url' => $url,
                ];
            })
            ->values();
    }
}
