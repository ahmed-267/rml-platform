<?php

namespace App\Models;

use App\Enums\TemplateDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'description',
        'active_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TemplateDocumentType::class,
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'active_version_id');
    }
}
