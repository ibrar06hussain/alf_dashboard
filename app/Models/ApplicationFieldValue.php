<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFieldValue extends Model
{
    protected $fillable = ['application_id', 'form_field_id', 'value'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
