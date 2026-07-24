<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadMetricValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'scheme_field_id',
        'key',
        'value',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function schemeField(): BelongsTo
    {
        return $this->belongsTo(SchemeField::class);
    }
}
