<?php

namespace App\Models;

use App\Enums\CadastralLookupStatus;
use App\Enums\GeocodingStatus;
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
        'lead_source',
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
        'formatted_address',
        'geocoding_status',
        'geocoded_at',
        'geocoding_error',
        'cadastral_reference',
        'cadastral_lookup_status',
        'cadastral_verified_at',
        'catastro_status',
        'catastro_provider',
        'catastro_checked_at',
        'catastro_matched_address',
        'catastro_municipality',
        'catastro_province',
        'catastro_postcode',
        'catastro_property_type',
        'catastro_built_area',
        'catastro_construction_year',
        'catastro_raw_response_json',
        'catastro_warnings_json',
        'catastro_error_message',
        'survey_eligibility_status',
        'property_type',
        'epc_rating',
        'size_m2',
        'submitted_property_area_m2',
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
            'geocoding_status' => GeocodingStatus::class,
            'cadastral_lookup_status' => CadastralLookupStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'size_m2' => 'decimal:2',
            'submitted_property_area_m2' => 'decimal:2',
            'catastro_built_area' => 'decimal:2',
            'catastro_raw_response_json' => 'array',
            'catastro_warnings_json' => 'array',
            'distance_km' => 'decimal:2',
            'buying_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'expected_margin' => 'decimal:2',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'listed_at' => 'datetime',
            'sold_at' => 'datetime',
            'geocoded_at' => 'datetime',
            'cadastral_verified_at' => 'datetime',
            'catastro_checked_at' => 'datetime',
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

    public function survey(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeadSurvey::class);
    }

    public function catastroSnapshots(): HasMany
    {
        return $this->hasMany(LeadCatastroSnapshot::class);
    }

    public function latestCatastroSnapshot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeadCatastroSnapshot::class)->latestOfMany();
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
