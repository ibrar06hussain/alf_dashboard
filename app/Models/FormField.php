<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'field_key', 'field_label', 'field_type', 'is_core',
        'is_mandatory', 'validation_rules', 'options_json', 'display_order',
        'placeholder', 'help_text',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_mandatory' => 'boolean',
            'validation_rules' => 'array',
            'options_json' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
