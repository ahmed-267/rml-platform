<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheme_id',
        'zone_id',
        'price_per_m2',
        'basic_price',
        'zone_factor',
        'size_factor',
        'distance_factor',
        'active',
        'effective_from',
        'effective_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_m2' => 'decimal:2',
            'basic_price' => 'decimal:2',
            'zone_factor' => 'decimal:4',
            'size_factor' => 'decimal:4',
            'distance_factor' => 'decimal:4',
            'active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
