<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerRevenue extends Model
{
    use HasFactory;

    protected $table = 'partner_revenue';

    protected $fillable = [
        'partner_team_id',
        'customer_team_id',
        'period_start',
        'period_end',
        'customer_revenue_cents',
        'partner_share_percent',
        'partner_revenue_cents',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'customer_revenue_cents' => 'integer',
            'partner_revenue_cents' => 'integer',
            'partner_share_percent' => 'decimal:2',
        ];
    }

    public function partnerTeam()
    {
        return $this->belongsTo(Team::class, 'partner_team_id');
    }

    public function customerTeam()
    {
        return $this->belongsTo(Team::class, 'customer_team_id');
    }
}
