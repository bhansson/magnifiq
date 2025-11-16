<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoStudioGeneration extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
        'product_id',
        'source_type',
        'source_reference',
        'prompt',
        'model',
        'storage_disk',
        'storage_path',
        'image_width',
        'image_height',
        'response_id',
        'response_model',
        'response_metadata',
        'product_ai_job_id',
        'parent_generation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'response_metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductAiJob::class, 'product_ai_job_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoStudioGeneration::class, 'parent_generation_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(PhotoStudioGeneration::class, 'parent_generation_id');
    }

    public function isEdit(): bool
    {
        return $this->parent_generation_id !== null;
    }
}
