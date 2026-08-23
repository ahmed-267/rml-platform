<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeadSurveyEvidence extends Model
{
    protected $fillable = [
        'lead_survey_id',
        'measurement_section_id',
        'category',
        'survey_section',
        'uploaded_by_user_id',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'orientation',
        'captured_at',
        'caption',
        'review_status',
        'auditor_comment',
        'for_ai_measurement',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'for_ai_measurement' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(LeadSurvey::class, 'lead_survey_id');
    }

    public function measurementSection(): BelongsTo
    {
        return $this->belongsTo(LeadSurveyMeasurementSection::class, 'measurement_section_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function temporaryUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(30));
        } catch (\Throwable) {
            return Storage::disk($this->disk)->url($this->path);
        }
    }
}
