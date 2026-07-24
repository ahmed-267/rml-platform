<?php

namespace App\Models;

use App\Enums\SchemeFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchemeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheme_id',
        'key',
        'label',
        'type',
        'options',
        'required',
        'active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SchemeFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function metricValues(): HasMany
    {
        return $this->hasMany(LeadMetricValue::class);
    }
}
