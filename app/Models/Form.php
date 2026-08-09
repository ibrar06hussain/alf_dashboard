<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = ['scheme_id', 'title', 'version', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('display_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FormDocument::class)->orderBy('display_order');
    }
}
