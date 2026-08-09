<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeInterest extends Model
{
    protected $fillable = [
        'scheme_id', 'full_name', 'cnic', 'phone',
        'district_id', 'email', 'message',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
