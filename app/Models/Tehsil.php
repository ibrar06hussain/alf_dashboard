<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tehsil extends Model
{
    public $timestamps = false;

    protected $fillable = ['district_id', 'name'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function unionCouncils(): HasMany
    {
        return $this->hasMany(UnionCouncil::class)->orderBy('name');
    }
}
