<?php

namespace App\Models;

use App\Enums\EvidenceFileType;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEvidenceFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'uploaded_by_user_id',
        'file_type',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'visibility',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => EvidenceFileType::class,
            'visibility' => EvidenceVisibility::class,
            'status' => EvidenceStatus::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
