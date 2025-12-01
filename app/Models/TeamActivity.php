<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TeamActivity extends Model
{
    use HasFactory;

    public const TYPE_JOB_QUEUED = 'job.queued';

    public const TYPE_JOB_COMPLETED = 'job.completed';

    public const TYPE_JOB_FAILED = 'job.failed';

    public const TYPE_FEED_IMPORTED = 'feed.imported';

    public const TYPE_FEED_REFRESHED = 'feed.refreshed';

    public const TYPE_FEED_DELETED = 'feed.deleted';

    public const TYPE_PHOTO_STUDIO_GENERATED = 'photo_studio.generated';

    public const TYPE_TEAM_MEMBER_ADDED = 'team.member_added';

    public const TYPE_TEAM_MEMBER_REMOVED = 'team.member_removed';

    protected $fillable = [
        'team_id',
        'user_id',
        'type',
        'subject_type',
        'subject_id',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get a human-readable description of the activity.
     */
    public function getDescriptionAttribute(): string
    {
        $userName = $this->user?->name ?? 'System';
        $props = $this->properties ?? [];

        return match ($this->type) {
            self::TYPE_JOB_QUEUED => sprintf(
                '%s queued a %s job for "%s"',
                $userName,
                $props['job_type'] ?? 'AI',
                $props['product_title'] ?? 'a product'
            ),
            self::TYPE_JOB_COMPLETED => sprintf(
                '%s job completed for "%s"',
                ucfirst($props['job_type'] ?? 'AI'),
                $props['product_title'] ?? 'a product'
            ),
            self::TYPE_JOB_FAILED => sprintf(
                '%s job failed for "%s"',
                ucfirst($props['job_type'] ?? 'AI'),
                $props['product_title'] ?? 'a product'
            ),
            self::TYPE_FEED_IMPORTED => sprintf(
                '%s imported feed "%s" with %d products',
                $userName,
                $props['feed_name'] ?? 'Unknown',
                $props['product_count'] ?? 0
            ),
            self::TYPE_FEED_REFRESHED => sprintf(
                '%s refreshed feed "%s"',
                $userName,
                $props['feed_name'] ?? 'Unknown'
            ),
            self::TYPE_FEED_DELETED => sprintf(
                '%s deleted feed "%s"',
                $userName,
                $props['feed_name'] ?? 'Unknown'
            ),
            self::TYPE_PHOTO_STUDIO_GENERATED => sprintf(
                '%s generated a new image%s',
                $userName,
                isset($props['product_title']) ? ' for "'.$props['product_title'].'"' : ''
            ),
            self::TYPE_TEAM_MEMBER_ADDED => sprintf(
                '%s joined the team',
                $props['member_name'] ?? 'A new member'
            ),
            self::TYPE_TEAM_MEMBER_REMOVED => sprintf(
                '%s left the team',
                $props['member_name'] ?? 'A member'
            ),
            default => 'Activity recorded',
        };
    }

    /**
     * Get the Heroicon name for the activity type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_JOB_QUEUED, self::TYPE_JOB_COMPLETED, self::TYPE_JOB_FAILED => 'cpu-chip',
            self::TYPE_FEED_IMPORTED, self::TYPE_FEED_REFRESHED, self::TYPE_FEED_DELETED => 'document-arrow-down',
            self::TYPE_PHOTO_STUDIO_GENERATED => 'photo',
            self::TYPE_TEAM_MEMBER_ADDED, self::TYPE_TEAM_MEMBER_REMOVED => 'user-group',
            default => 'bolt',
        };
    }

    /**
     * Get the color class based on activity type.
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_JOB_COMPLETED, self::TYPE_PHOTO_STUDIO_GENERATED => 'green',
            self::TYPE_JOB_FAILED => 'red',
            self::TYPE_JOB_QUEUED => 'yellow',
            self::TYPE_FEED_IMPORTED, self::TYPE_FEED_REFRESHED => 'blue',
            self::TYPE_FEED_DELETED => 'orange',
            self::TYPE_TEAM_MEMBER_ADDED => 'indigo',
            self::TYPE_TEAM_MEMBER_REMOVED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Record a job completion activity.
     */
    public static function recordJobCompleted(ProductAiJob $job, ?int $userId = null): self
    {
        return static::create([
            'team_id' => $job->team_id,
            'user_id' => $userId,
            'type' => self::TYPE_JOB_COMPLETED,
            'subject_type' => ProductAiJob::class,
            'subject_id' => $job->id,
            'properties' => [
                'job_type' => $job->job_type,
                'product_title' => $job->product?->title,
                'template_name' => $job->template?->name,
            ],
        ]);
    }

    /**
     * Record a job failure activity.
     */
    public static function recordJobFailed(ProductAiJob $job, ?int $userId = null): self
    {
        return static::create([
            'team_id' => $job->team_id,
            'user_id' => $userId,
            'type' => self::TYPE_JOB_FAILED,
            'subject_type' => ProductAiJob::class,
            'subject_id' => $job->id,
            'properties' => [
                'job_type' => $job->job_type,
                'product_title' => $job->product?->title,
                'error' => $job->last_error,
            ],
        ]);
    }

    /**
     * Record a feed import activity.
     */
    public static function recordFeedImported(ProductFeed $feed, int $userId, int $productCount): self
    {
        return static::create([
            'team_id' => $feed->team_id,
            'user_id' => $userId,
            'type' => self::TYPE_FEED_IMPORTED,
            'subject_type' => ProductFeed::class,
            'subject_id' => $feed->id,
            'properties' => [
                'feed_name' => $feed->name,
                'product_count' => $productCount,
            ],
        ]);
    }

    /**
     * Record a Photo Studio generation activity.
     */
    public static function recordPhotoStudioGenerated(
        PhotoStudioGeneration $generation,
        ?int $userId = null
    ): self {
        return static::create([
            'team_id' => $generation->team_id,
            'user_id' => $userId ?? $generation->user_id,
            'type' => self::TYPE_PHOTO_STUDIO_GENERATED,
            'subject_type' => PhotoStudioGeneration::class,
            'subject_id' => $generation->id,
            'properties' => [
                'product_title' => $generation->product?->title,
                'model' => $generation->model,
            ],
        ]);
    }
}
