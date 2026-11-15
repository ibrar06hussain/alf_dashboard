<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationMember extends Model
{
    protected $fillable = [
        'application_id',
        'member_order',
        'role',
        'full_name',
        'father_name',
        'cnic',
        'phone',
        'gender',
        'village',
        'land_kanals',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'member_order' => 'integer',
            'land_kanals' => 'decimal:2',
            'extra_data' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

