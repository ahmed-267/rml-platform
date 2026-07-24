<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAuditChecklistResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_audit_id',
        'audit_checklist_item_id',
        'checked',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked' => 'boolean',
        ];
    }

    public function leadAudit(): BelongsTo
    {
        return $this->belongsTo(LeadAudit::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id');
    }
}
