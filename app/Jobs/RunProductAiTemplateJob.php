<?php

namespace App\Jobs;

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatRequest;
use App\Facades\AI;
use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Models\TeamActivity;
use App\Support\ProductAiContentParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunProductAiTemplateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Backoff intervals (seconds) between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 600];

    public function __construct(public int $productAiJobId)
    {
        $this->productAiJobId = $productAiJobId;
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $jobRecord = ProductAiJob::query()
            ->with('template')
            ->findOrFail($this->productAiJobId);

        $template = $jobRecord->template;

        if (! $template || ! $template->is_active) {
            throw new RuntimeException('The selected AI template is no longer available.');
        }

        $jobRecord->markProcessing([], [
            'attempts' => $this->attempts(),
            'progress' => 10,
        ]);

        $product = Product::query()
            ->with(['team', 'feed'])
            ->findOrFail($jobRecord->product_id);

        try {
            // Build the chat request using the new DTOs
            $request = $this->buildChatRequest($template, $product);

            // Execute via AI abstraction
            $response = AI::forFeature('chat')->chat($request);

            // Check for truncation
            if ($response->wasTruncated()) {
                Log::warning('AI provider response hit token limit', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'template_id' => $template->id,
                    'template_slug' => $template->slug,
                    'finish_reason' => $response->finishReason,
                ]);

                throw new RuntimeException('AI response exceeded the configured token limit.');
            }

            if ($response->content === '') {
                Log::warning('AI provider response missing content', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'template_id' => $template->id,
                    'template_slug' => $template->slug,
                ]);

                throw new RuntimeException('Received empty content from the AI provider.');
            }

            $jobRecord->updateProgress(80);

            $contentPayload = ProductAiContentParser::normalize($template->contentType(), $response->content);

            $model = Arr::get($template->settings, 'model', config('ai.features.chat.model', 'openrouter/auto'));

            $record = ProductAiGeneration::create([
                'team_id' => $product->team_id,
                'product_id' => $product->id,
                'product_ai_template_id' => $template->id,
                'product_ai_job_id' => $jobRecord->id,
                'sku' => $product->sku,
                'content' => $contentPayload,
                'meta' => [
                    'model' => $response->model ?? $model,
                    'template_slug' => $template->slug,
                    'job_id' => $jobRecord->id,
                ],
            ]);

            $this->trimHistory($template, $product->id, max($template->historyLimit(), 1));

            $jobRecord->markCompleted([
                'generation_id' => $record->id,
            ]);

            TeamActivity::recordJobCompleted($jobRecord);
        } catch (Throwable $e) {
            $jobRecord->markFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $jobRecord = ProductAiJob::query()->find($this->productAiJobId);

        if (! $jobRecord) {
            return;
        }

        $jobRecord->markFailed($exception->getMessage());
    }

    /**
     * Build the chat request from template and product.
     */
    protected function buildChatRequest(ProductAiTemplate $template, Product $product): ChatRequest
    {
        $userTemplate = trim((string) $template->prompt);

        if ($userTemplate === '') {
            throw new RuntimeException('Template prompt is empty.');
        }

        $placeholders = $this->buildPlaceholders($template, $product);
        $userPrompt = strtr($userTemplate, $placeholders);
        $systemPrompt = trim((string) $template->system_prompt);

        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = ChatMessage::system(strtr($systemPrompt, $placeholders));
        }

        $messages[] = ChatMessage::user(trim($userPrompt));

        $options = $template->options();
        unset($options['timeout']);

        $model = Arr::get($template->settings, 'model', config('ai.features.chat.model', 'openrouter/auto'));

        return new ChatRequest(
            messages: $messages,
            model: $model,
            maxTokens: $options['max_tokens'] ?? null,
            temperature: $options['temperature'] ?? null,
            extra: array_diff_key($options, ['max_tokens' => 1, 'temperature' => 1]),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function buildPlaceholders(ProductAiTemplate $template, Product $product): array
    {
        $placeholders = [];
        $context = $template->context ?? [];

        foreach ($context as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = (string) ($definition['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $placeholders['{{ '.$key.' }}'] = $this->resolveContextValue($key, $definition, $template, $product);
        }

        $pattern = '/{{\s*(.+?)\s*}}/u';
        $templates = array_filter([
            $template->prompt,
            $template->system_prompt,
        ]);

        foreach ($templates as $templateString) {
            if (! is_string($templateString)) {
                continue;
            }

            if (preg_match_all($pattern, $templateString, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $rawKey) {
                $key = trim((string) $rawKey);

                if ($key === '') {
                    continue;
                }

                $placeholder = '{{ '.$key.' }}';

                if (array_key_exists($placeholder, $placeholders)) {
                    continue;
                }

                $placeholders[$placeholder] = $this->resolveContextValue($key, [], $template, $product);
            }
        }

        return $placeholders;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function resolveContextValue(string $key, array $definition, ProductAiTemplate $template, Product $product): string
    {
        $variables = config('product-ai.context_variables', []);
        $variable = $variables[$key] ?? null;
        $attribute = $variable['attribute'] ?? $key;
        $default = $definition['default'] ?? ($variable['default'] ?? 'N/A');

        $rawValue = data_get($product, $attribute);

        if (is_string($rawValue)) {
            $rawValue = trim($rawValue);
        }

        if ($rawValue === null || $rawValue === '') {
            $rawValue = $default;
        }

        $cleanMode = $definition['clean'] ?? ($variable['clean'] ?? 'single_line');

        if ($cleanMode === 'multiline') {
            $value = $this->cleanMultiline((string) $rawValue);
        } else {
            $value = $this->cleanSingleLine((string) $rawValue);
        }

        $shouldExcerpt = (bool) ($definition['excerpt'] ?? $variable['excerpt'] ?? false);
        $limit = (int) ($definition['limit'] ?? $variable['limit'] ?? config('product-ai.defaults.description_excerpt_limit', 600));

        if ($shouldExcerpt && $limit > 0 && $rawValue !== $default) {
            $value = Str::limit($value, $limit);
        }

        if ($value === '') {
            return (string) $default;
        }

        return $value;
    }

    protected function trimHistory(ProductAiTemplate $template, int $productId, int $keep): void
    {
        $keep = $keep > 0 ? $keep : 1;

        $idsToRemove = ProductAiGeneration::query()
            ->where('product_id', $productId)
            ->where('product_ai_template_id', $template->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX) // Required for SQLite compatibility (OFFSET needs LIMIT)
            ->pluck('id');

        if ($idsToRemove->isEmpty()) {
            return;
        }

        ProductAiGeneration::query()
            ->whereIn('id', $idsToRemove)
            ->delete();
    }

    protected function cleanMultiline(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim(preg_replace("/[ \t]+/", ' ', $value));
    }

    protected function cleanSingleLine(string $value): string
    {
        return trim(preg_replace("/\s+/", ' ', strip_tags($value)));
    }
}
