<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_reference',
        'submitted_by_user_id',
        'seller_company_id',
        'scheme_id',
        'zone_id',
        'status',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'customer_whatsapp',
        'customer_email',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'latitude',
        'longitude',
        'property_type',
        'epc_rating',
        'size_m2',
        'distance_km',
        'buying_price',
        'selling_price',
        'expected_margin',
        'notes',
        'rejection_reason',
        'rejection_reason_code',
        'rejection_comment',
        'accepted_at',
        'rejected_at',
        'rejected_by_user_id',
        'listed_at',
        'sold_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'size_m2' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'buying_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'expected_margin' => 'decimal:2',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'listed_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function sellerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_company_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function metricValues(): HasMany
    {
        return $this->hasMany(LeadMetricValue::class);
    }

    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(LeadEvidenceFile::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LeadAudit::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(LeadPackage::class, 'package_leads')->withTimestamps();
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
