<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    protected $fillable = [
        'cnic', 'full_name', 'father_name', 'phone', 'phone_verified',
        'district_id', 'tehsil', 'union_council', 'village', 'address',
        'land_location', 'email', 'gender',
    ];

    protected function casts(): array
    {
        return ['phone_verified' => 'boolean'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
