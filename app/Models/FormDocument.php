<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormDocument extends Model
{
    protected $fillable = [
        'form_id', 'document_name', 'is_mandatory',
        'allowed_extensions', 'max_size_mb', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function allowedExtensionsArray(): array
    {
        return array_filter(array_map('trim', explode(',', strtolower($this->allowed_extensions))));
    }
}
