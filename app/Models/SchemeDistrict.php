<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeDistrict extends Model
{
    protected $fillable = ['scheme_id', 'district_id', 'quota'];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
