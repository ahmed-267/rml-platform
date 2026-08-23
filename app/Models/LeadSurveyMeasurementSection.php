<?php

namespace App\Models;

use App\Enums\SurveyMeasurementMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSurveyMeasurementSection extends Model
{
    protected $fillable = [
        'lead_survey_id',
        'name',
        'section_type',
        'length_m',
        'width_m',
        'height_m',
        'calculated_area_m2',
        'manual_area_m2',
        'measurement_method',
        'confidence',
        'is_estimate',
        'area_not_accessed',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'measurement_method' => SurveyMeasurementMethod::class,
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'height_m' => 'decimal:2',
            'calculated_area_m2' => 'decimal:2',
            'manual_area_m2' => 'decimal:2',
            'is_estimate' => 'boolean',
            'area_not_accessed' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(LeadSurvey::class, 'lead_survey_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(LeadSurveyEvidence::class, 'measurement_section_id');
    }
}
