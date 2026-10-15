<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormDocument extends Model
{
    protected $fillable = [
        'form_id', 'document_name', 'document_name_ur', 'is_mandatory',
        'allowed_extensions', 'max_size_mb', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }

    public function getUrduName(): ?string
    {
        if (! empty($this->document_name_ur)) {
            return $this->document_name_ur;
        }

        if (stripos($this->document_name, 'cnic') !== false) {
            return 'شناختی کارڈ کی کاپی';
        }

        return null;
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
