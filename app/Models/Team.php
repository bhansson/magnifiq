<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'public_hash',
        'personal_team',
        'type',
        'parent_team_id',
        'logo_path',
        'partner_slug',
        'user_id',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'customer',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Team $team): void {
            if (! $team->public_hash) {
                $team->public_hash = static::generateUniquePublicHash();
            }
        });
    }

    protected static function generateUniquePublicHash(): string
    {
        do {
            $hash = Str::lower(Str::random(32));
        } while (static::query()->where('public_hash', $hash)->exists());

        return $hash;
    }

    public function productFeeds()
    {
        return $this->hasMany(ProductFeed::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function isPartner(): bool
    {
        return $this->type === 'partner';
    }

    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }

    public function parentTeam()
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'parent_team_id');
    }

    public function revenueRecords()
    {
        return $this->hasMany(PartnerRevenue::class, 'partner_team_id');
    }

    public function revenueAsCustomer()
    {
        return $this->hasMany(PartnerRevenue::class, 'customer_team_id');
    }
}
