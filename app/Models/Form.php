<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'scheme_id',
        'title',
        'version',
        'is_published',
        'is_group_application',
        'min_group_members',
        'max_group_members',
        'group_section_title',
        'group_section_title_ur',
        'member_custom_fields_json',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_group_application' => 'boolean',
            'min_group_members' => 'integer',
            'max_group_members' => 'integer',
            'member_custom_fields_json' => 'array',
        ];
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
