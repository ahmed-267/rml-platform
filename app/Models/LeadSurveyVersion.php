<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSurveyVersion extends Model
{
    protected $fillable = [
        'lead_survey_id',
        'version',
        'status',
        'event',
        'snapshot',
        'actor_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(LeadSurvey::class, 'lead_survey_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
