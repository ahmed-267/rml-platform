<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'approval_status',
        'contact_name',
        'email',
        'phone',
        'whatsapp',
        'address',
        'city',
        'postcode',
        'country',
        'latitude',
        'longitude',
        'notes',
        'approved_at',
        'approved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'approval_status' => ApprovalStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'approved_at' => 'datetime',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sellerProfiles(): HasMany
    {
        return $this->hasMany(SellerProfile::class);
    }

    public function buyerProfiles(): HasMany
    {
        return $this->hasMany(BuyerProfile::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(SellerStaffInvitation::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'seller_company_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'buyer_company_id');
    }
}
