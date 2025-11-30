<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ProductAiJob extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_PHOTO_STUDIO = 'photo_studio';

    public const TYPE_VISION_PROMPT = 'vision_prompt';

    protected $fillable = [
        'team_id',
        'product_id',
        'sku',
        'product_ai_template_id',
        'job_type',
        'status',
        'progress',
        'attempts',
        'queued_at',
        'started_at',
        'finished_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $attributes = [
        'job_type' => self::TYPE_TEMPLATE,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductAiTemplate::class, 'product_ai_template_id');
    }

    public function photoStudioGeneration(): HasOne
    {
        return $this->hasOne(PhotoStudioGeneration::class, 'product_ai_job_id');
    }

    public function runtimeForHumans(?CarbonInterface $reference = null, int $parts = 2): ?string
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->finished_at ?? $reference ?? now();

        return $this->started_at->diffForHumans(
            $end,
            [
                'parts' => $parts,
                'join' => true,
                'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            ]
        );
    }

    public function friendlyErrorMessage(): ?string
    {
        if ($this->status !== self::STATUS_FAILED) {
            return null;
        }

        $error = Str::lower($this->last_error ?? '');

        return match (true) {
            $error === '' => 'The job stopped before it could finish.',
            Str::contains($error, ['timeout', 'timed out']) => 'The job timed out before the AI responded.',
            Str::contains($error, ['rate limit', 'too many requests', '429']) => 'We hit the AI rate limit; wait a moment and try again.',
            Str::contains($error, ['unauthorized', '401', 'invalid api key']) => 'We could not reach the AI service with the current credentials.',
            Str::contains($error, ['payment required', '402', 'insufficient', 'credits']) => 'The AI service ran out of credits or the request needs fewer tokens.',
            Str::contains($error, ['400', 'bad request', 'invalid parameter', 'rejected the request']) => 'The AI request parameters were invalid. Refresh and try again.',
            Str::contains($error, ['403', 'flagged', 'moderation']) => 'The AI provider flagged the content for moderation.',
            Str::contains($error, ['502', 'model could not be reached']) => 'The selected AI model is temporarily unavailable.',
            Str::contains($error, ['503', 'no available provider']) => 'No AI provider is currently available for the selected model.',
            default => 'Something went wrong while generating the content. Please try again.',
        };
    }

    /**
     * Mark the job as processing.
     *
     * @param  array<string, mixed>  $meta  Additional metadata to merge
     * @param  array<string, mixed>  $additional  Additional fields to set (e.g., attempts, progress)
     */
    public function markProcessing(array $meta = [], array $additional = []): void
    {
        $data = [
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
            'last_error' => null,
        ];

        if (! empty($meta)) {
            $data['meta'] = array_merge($this->meta ?? [], $meta);
        }

        $this->forceFill(array_merge($data, $additional))->save();
    }

    /**
     * Mark the job as completed.
     *
     * @param  array<string, mixed>  $meta  Additional metadata to merge
     */
    public function markCompleted(array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'finished_at' => now(),
            'meta' => array_merge($this->meta ?? [], $meta),
        ])->save();
    }

    /**
     * Mark the job as failed.
     *
     * @param  array<string, mixed>  $meta  Additional metadata to merge
     */
    public function markFailed(string $error, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'progress' => 0,
            'finished_at' => now(),
            'last_error' => Str::limit($error, 500),
            'meta' => array_merge($this->meta ?? [], $meta),
        ])->save();
    }

    /**
     * Update the job progress.
     */
    public function updateProgress(int $progress, array $meta = []): void
    {
        $data = ['progress' => min(100, max(0, $progress))];

        if (! empty($meta)) {
            $data['meta'] = array_merge($this->meta ?? [], $meta);
        }

        $this->forceFill($data)->save();
    }
}
