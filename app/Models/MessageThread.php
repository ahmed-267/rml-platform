<?php

namespace App\Models;

use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_reference',
        'subject',
        'category',
        'status',
        'created_by_user_id',
        'assigned_to_user_id',
        'related_lead_id',
        'related_purchase_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MessageThreadCategory::class,
            'status' => MessageThreadStatus::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function relatedLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'related_lead_id');
    }

    public function relatedPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'related_purchase_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
