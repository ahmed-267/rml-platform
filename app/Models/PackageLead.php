<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_package_id',
        'lead_id',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(LeadPackage::class, 'lead_package_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
