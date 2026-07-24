<?php

namespace App\Models;

use App\Enums\AuditDecisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'auditor_user_id',
        'final_decision_by_user_id',
        'status',
        'audit_notes',
        'rejection_reason',
        'requested_info',
        'buying_price',
        'selling_price',
        'suggested_price',
        'expected_margin',
        'override_reason',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuditDecisionStatus::class,
            'buying_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'suggested_price' => 'decimal:2',
            'expected_margin' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_user_id');
    }

    public function finalDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_decision_by_user_id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(LeadAuditChecklistResult::class);
    }
}
