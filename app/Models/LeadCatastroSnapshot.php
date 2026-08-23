<?php

namespace App\Models;

use App\Enums\CatastroProvider;
use App\Enums\CatastroSearchMethod;
use App\Enums\CatastroVerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadCatastroSnapshot extends Model
{
    protected $fillable = [
        'lead_id',
        'provider',
        'search_method',
        'search_input',
        'verification_status',
        'cadastral_reference',
        'cadastral_address',
        'province',
        'municipality',
        'street_type',
        'street_name',
        'street_number',
        'block',
        'staircase',
        'floor',
        'door',
        'postcode',
        'unit_label',
        'property_use',
        'constructed_area_m2',
        'parcel_area_m2',
        'construction_year',
        'geometry',
        'match_summary',
        'raw_payload',
        'warnings',
        'provider_request_id',
        'coordinate_distance_m',
        'is_current',
        'is_selected',
        'selected_at',
        'selected_by_user_id',
        'auditor_confirmed_at',
        'auditor_confirmed_by_user_id',
        'auditor_notes',
        'lookup_at',
        'looked_up_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CatastroProvider::class,
            'search_method' => CatastroSearchMethod::class,
            'verification_status' => CatastroVerificationStatus::class,
            'constructed_area_m2' => 'decimal:2',
            'parcel_area_m2' => 'decimal:2',
            'coordinate_distance_m' => 'decimal:2',
            'geometry' => 'array',
            'raw_payload' => 'array',
            'search_input' => 'array',
            'warnings' => 'array',
            'is_current' => 'boolean',
            'is_selected' => 'boolean',
            'lookup_at' => 'datetime',
            'selected_at' => 'datetime',
            'auditor_confirmed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function lookedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'looked_up_by_user_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function auditorConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_confirmed_by_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public static function supportsCurrentFlag(): bool
    {
        return Schema::hasColumn((new self)->getTable(), 'is_current');
    }

    /**
     * Mark this snapshot as the only current snapshot for its lead.
     */
    public function markAsCurrent(): void
    {
        if (! self::supportsCurrentFlag()) {
            return;
        }

        DB::transaction(function (): void {
            self::query()
                ->where('lead_id', $this->lead_id)
                ->lockForUpdate()
                ->get(['id']);

            self::query()
                ->where('lead_id', $this->lead_id)
                ->where('is_current', true)
                ->where('id', '!=', $this->id)
                ->update(['is_current' => false]);

            $this->forceFill(['is_current' => true])->save();
        });
    }
}
