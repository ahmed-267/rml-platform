<?php

namespace App\Models;

use App\Enums\CommissionAppliesTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'applies_to',
        'percentage',
        'rate_per_m2',
        'active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_to' => CommissionAppliesTo::class,
            'percentage' => 'decimal:2',
            'rate_per_m2' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
