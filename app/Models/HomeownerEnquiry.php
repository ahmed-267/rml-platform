<?php

namespace App\Models;

use App\Enums\HomeownerEnquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeownerEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'name',
        'phone',
        'email',
        'address',
        'postcode',
        'service_interested_in',
        'message',
        'consent',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'status' => HomeownerEnquiryStatus::class,
        ];
    }
}
