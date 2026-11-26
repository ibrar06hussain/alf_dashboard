<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'field_key', 'field_label', 'field_label_ur', 'field_type', 'is_core',
        'is_mandatory', 'validation_rules', 'options_json', 'option_configs_json',
        'is_billable_items_field', 'display_order',
        'placeholder', 'placeholder_ur', 'help_text', 'help_text_ur',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_billable_items_field' => 'boolean',
            'validation_rules' => 'array',
            'options_json' => 'array',
            'option_configs_json' => 'array',
        ];
    }

    /**
     * Return structured option metadata with pricing/input defaults for every option.
     *
     * @return array<int, array<string, mixed>>
     */
    public function normalizedOptionConfigs(): array
    {
        $configs = $this->option_configs_json;
        if (is_array($configs) && ! empty($configs)) {
            $normalized = [];
            foreach ($configs as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $pricingMode = ($item['pricing_mode'] ?? 'free') === 'paid' ? 'paid' : 'free';
                $unitPrice = $pricingMode === 'paid' ? max(0.0, (float) ($item['unit_price'] ?? 0)) : 0.0;
                if ($unitPrice <= 0) {
                    $pricingMode = 'free';
                }
                $govtShare = isset($item['govt_share_pct']) && $item['govt_share_pct'] !== '' && $item['govt_share_pct'] !== null
                    ? max(0.0, min(100.0, (float) $item['govt_share_pct']))
                    : null;

                $key = trim((string) ($item['key'] ?? '')) ?: ApplicationItem::normalizeKey($label);

                $normalized[] = [
                    'key' => $key,
                    'label' => $label,
                    'label_ur' => trim((string) ($item['label_ur'] ?? '')) ?: null,
                    'input_mode' => ($item['input_mode'] ?? 'text') === 'numeric_qty' ? 'numeric_qty' : 'text',
                    'pricing_mode' => $pricingMode,
                    'unit_price' => $unitPrice,
                    'govt_share_pct' => $govtShare,
                    'unit_label' => trim((string) ($item['unit_label'] ?? '')) ?: 'Units',
                ];
            }

            if (! empty($normalized)) {
                return $normalized;
            }
        }

        $plainOptions = is_array($this->options_json) ? $this->options_json : [];

        return array_values(array_filter(array_map(function ($label) {
            $clean = trim((string) $label);
            if ($clean === '') {
                return null;
            }

            return [
                'key' => ApplicationItem::normalizeKey($clean),
                'label' => $clean,
                'label_ur' => null,
                'input_mode' => $this->is_billable_items_field ? 'numeric_qty' : 'text',
                'pricing_mode' => 'free',
                'unit_price' => 0.0,
                'govt_share_pct' => null,
                'unit_label' => 'Units',
            ];
        }, $plainOptions)));
    }

    public function resolveOptionConfig(string $labelOrKey): ?array
    {
        $needle = strtolower(trim($labelOrKey));
        if ($needle === '') {
            return null;
        }

        $configs = $this->normalizedOptionConfigs();

        // Pass 1: exact display label match takes precedence over legacy option keys
        foreach ($configs as $cfg) {
            if (strtolower(trim((string) $cfg['label'])) === $needle) {
                return $cfg;
            }
        }

        // Pass 2: fallback to normalized key / exact key match
        $normalizedNeedle = ApplicationItem::normalizeKey($labelOrKey);
        foreach ($configs as $cfg) {
            $cfgKey = strtolower(trim((string) $cfg['key']));
            if ($cfgKey === $needle || $cfgKey === $normalizedNeedle) {
                return $cfg;
            }
        }

        return null;
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
