<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'field_key', 'field_label', 'field_label_ur', 'field_type', 'is_core',
        'is_mandatory', 'validation_rules', 'options_json', 'display_order',
        'placeholder', 'placeholder_ur', 'help_text', 'help_text_ur',
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

    public function getUrduLabel(): ?string
    {
        if (! empty($this->field_label_ur)) {
            return $this->field_label_ur;
        }

        $coreDefs = collect(\App\Support\CoreFormFields::definitions())->keyBy('field_key');
        if ($coreDefs->has($this->field_key) && ! empty($coreDefs->get($this->field_key)['field_label_ur'])) {
            return $coreDefs->get($this->field_key)['field_label_ur'];
        }

        if (\Illuminate\Support\Facades\Lang::has('portal.' . $this->field_key, 'ur')) {
            return __('portal.' . $this->field_key, [], 'ur');
        }

        return null;
    }

    public function getUrduPlaceholder(): ?string
    {
        if (! empty($this->placeholder_ur)) {
            return $this->placeholder_ur;
        }

        $coreDefs = collect(\App\Support\CoreFormFields::definitions())->keyBy('field_key');
        if ($coreDefs->has($this->field_key) && ! empty($coreDefs->get($this->field_key)['placeholder_ur'])) {
            return $coreDefs->get($this->field_key)['placeholder_ur'];
        }

        return null;
    }
}
