<?php

namespace App\Models;

use App\Enums\PackageStatus;
use App\Enums\PackageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_reference',
        'name',
        'package_type',
        'scheme_id',
        'requested_leads_count',
        'size_range_min',
        'size_range_max',
        'distance_range_min',
        'distance_range_max',
        'zone_mix',
        'avg_price_per_m2',
        'estimated_total',
        'status',
        'created_by_user_id',
        'buyer_company_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_type' => PackageType::class,
            'status' => PackageStatus::class,
            'zone_mix' => 'array',
            'size_range_min' => 'decimal:2',
            'size_range_max' => 'decimal:2',
            'distance_range_min' => 'decimal:2',
            'distance_range_max' => 'decimal:2',
            'avg_price_per_m2' => 'decimal:2',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'package_leads')->withTimestamps();
    }

    public function packageLeads(): HasMany
    {
        return $this->hasMany(PackageLead::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
