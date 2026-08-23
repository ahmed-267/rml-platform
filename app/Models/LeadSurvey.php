<?php

namespace App\Models;

use App\Enums\SurveyMeasurementMethod;
use App\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSurvey extends Model
{
    protected $fillable = [
        'lead_id',
        'status',
        'version',
        'current_step',
        'surveyor_user_id',
        'assigned_by_user_id',
        'survey_date',
        'started_at',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'last_saved_at',
        'last_edited_by_user_id',
        'reviewed_by_user_id',
        'confirmed_address',
        'property_type',
        'occupancy_type',
        'number_of_floors',
        'approx_construction_year',
        'occupied',
        'homeowner_present',
        'general_condition',
        'location_confirmed',
        'discrepancy_notes',
        'access',
        'no_access',
        'loft_hatch',
        'surveyed_floor_area_m2',
        'surveyed_installation_area_m2',
        'measurement_method',
        'measurement_confidence',
        'measurement_date',
        'measurement_notes',
        'scheme_inspection',
        'homeowner_confirmation',
        'risks',
        'surveyor_recommendation',
        'seller_visible_notes',
        'buyer_visible_notes',
        'internal_audit_notes',
        'auditor_approved_area_m2',
        'auditor_approved_installation_area_m2',
        'auditor_decision_notes',
        'correction_request',
        'correction_sections',
        'ai_suggestions',
    ];

    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
            'measurement_method' => SurveyMeasurementMethod::class,
            'survey_date' => 'date',
            'measurement_date' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'occupied' => 'boolean',
            'homeowner_present' => 'boolean',
            'location_confirmed' => 'boolean',
            'access' => 'array',
            'no_access' => 'array',
            'loft_hatch' => 'array',
            'scheme_inspection' => 'array',
            'homeowner_confirmation' => 'array',
            'risks' => 'array',
            'correction_sections' => 'array',
            'ai_suggestions' => 'array',
            'surveyed_floor_area_m2' => 'decimal:2',
            'surveyed_installation_area_m2' => 'decimal:2',
            'auditor_approved_area_m2' => 'decimal:2',
            'auditor_approved_installation_area_m2' => 'decimal:2',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_user_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LeadSurveyVersion::class);
    }

    public function measurementSections(): HasMany
    {
        return $this->hasMany(LeadSurveyMeasurementSection::class)->orderBy('sort_order');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(LeadSurveyEvidence::class);
    }
}
