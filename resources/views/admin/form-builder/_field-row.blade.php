@php
    $isCore = $field?->is_core ?? false;
    $options = is_array($field?->options_json ?? null) ? implode(', ', $field->options_json) : '';
    $urduLabelValue = $field?->field_label_ur ?? ($field ? $field->getUrduLabel() : '');
    $urduPlaceholderValue = $field?->placeholder_ur ?? ($field ? $field->getUrduPlaceholder() : '');
@endphp
<div class="field-row border rounded p-3 mb-3 bg-light shadow-sm" @if($isCore) data-core="1" @endif>
    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
        <strong class="drag-handle text-dark" style="cursor:grab;">
            <i class="fa-solid fa-bars me-2 text-muted"></i>
            @if($isCore)
                <span class="badge bg-secondary me-1">Core Field</span>
                <span class="text-muted small">({{ $field?->field_key }})</span>
            @else
                <span class="badge bg-primary me-1">Custom Field</span>
            @endif
        </strong>
        @unless($isCore)
            <button type="button" class="btn btn-sm btn-outline-danger remove-field">
                <i class="fa-solid fa-trash-can me-1"></i> Remove
            </button>
        @endunless
    </div>

    <input type="hidden" name="fields[{{ $index }}][id]" value="{{ $field?->id ?? '' }}">
    <input type="hidden" name="fields[{{ $index }}][display_order]" data-order value="{{ $field?->display_order ?? ($index === '__INDEX__' ? 0 : $index + 1) }}">

    {{-- Main Row: Key, English Label, Urdu Label, Type, Mandatory --}}
    <div class="row g-2 mb-2">
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label small fw-semibold text-muted">Field Key</label>
            <input type="text" name="fields[{{ $index }}][field_key]" class="form-control form-control-sm font-monospace"
                   value="{{ $field?->field_key ?? '' }}" {{ $isCore ? 'readonly' : 'required' }}>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small fw-semibold">Label (English) <span class="text-danger">*</span></label>
            <input type="text" name="fields[{{ $index }}][field_label]" class="form-control form-control-sm"
                   value="{{ $field?->field_label ?? '' }}" {{ $isCore ? 'readonly' : 'required' }}>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small fw-semibold text-success">
                لیبل (اردو ترجمہ / Urdu) <span class="badge bg-success-subtle text-success">Urdu</span>
            </label>
            <input type="text" name="fields[{{ $index }}][field_label_ur]" class="form-control form-control-sm"
                   dir="rtl" value="{{ $urduLabelValue }}" placeholder="مثلاً پورا نام"
                   style="font-family: 'Noto Nastaliq Urdu', Tahoma, sans-serif;">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small fw-semibold text-muted">Type</label>
            <select name="fields[{{ $index }}][field_type]" class="form-select form-select-sm js-field-type-select" {{ $isCore ? 'disabled' : '' }}>
                @foreach($fieldTypes as $type)
                    @php
                        $typeName = match($type) {
                            'checkbox_with_text' => 'Multiselect Checkbox with Text',
                            'checkbox' => 'Checkbox (Multi-select)',
                            'textarea' => 'Textarea (Multi-line)',
                            'select' => 'Dropdown Select',
                            'switch' => 'Toggle Switch',
                            'district' => 'District (Dropdown)',
                            'tehsil' => 'Tehsil (Cascading)',
                            'union_council' => 'Union Council (Cascading)',
                            'cnic' => 'CNIC (13 Digits)',
                            'phone' => 'Phone / Mobile',
                            default => ucfirst(str_replace('_', ' ', $type)),
                        };
                    @endphp
                    <option value="{{ $type }}" @selected(($field?->field_type ?? 'text') === $type)>{{ $typeName }}</option>
                @endforeach
            </select>
            @if($isCore)
                <input type="hidden" name="fields[{{ $index }}][field_type]" value="{{ $field?->field_type }}">
            @endif
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small fw-semibold d-block text-muted">Mandatory</label>
            <div class="form-check mt-1">
                <input type="checkbox" class="form-check-input" name="fields[{{ $index }}][is_mandatory]" value="1"
                    {{ ($field?->is_mandatory ?? false) ? 'checked' : '' }} {{ $isCore ? 'disabled' : '' }}>
                @if($isCore)
                    <input type="hidden" name="fields[{{ $index }}][is_mandatory]" value="{{ ($field?->is_mandatory ?? false) ? '1' : '0' }}">
                @endif
                <label class="form-check-label small">Required</label>
            </div>
        </div>
    </div>

    {{-- Secondary Row: English Placeholder, Urdu Placeholder, English Help, Urdu Help --}}
    <div class="row g-2">
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-muted mb-1">Placeholder (English)</label>
            <input type="text" name="fields[{{ $index }}][placeholder]" class="form-control form-control-sm"
                   value="{{ $field?->placeholder ?? '' }}" placeholder="e.g. Enter value">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-success mb-1">پلیس ہولڈر (اردو / Urdu Placeholder)</label>
            <input type="text" name="fields[{{ $index }}][placeholder_ur]" class="form-control form-control-sm"
                   dir="rtl" value="{{ $urduPlaceholderValue }}" placeholder="مثلاً درج کریں">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-muted mb-1">Help Text (English)</label>
            <input type="text" name="fields[{{ $index }}][help_text]" class="form-control form-control-sm"
                   value="{{ $field?->help_text ?? '' }}" placeholder="Optional hint">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-success mb-1">رہنمائی متن (اردو / Urdu Help)</label>
            <input type="text" name="fields[{{ $index }}][help_text_ur]" class="form-control form-control-sm"
                   dir="rtl" value="{{ $field?->help_text_ur ?? '' }}" placeholder="اختیاری وضاحت">
        </div>
        @unless($isCore)
            @php
                $optionConfigs = $field?->option_configs_json ?? [];
                $isBillableMatrix = (bool) ($field?->is_billable_items_field ?? false);
            @endphp
            <div class="col-12 mt-2 js-checkbox-text-info {{ ($field?->field_type ?? '') === 'checkbox_with_text' ? '' : 'd-none' }}">
                <div class="alert alert-info py-2 px-3 small mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-info text-info fs-5"></i>
                        <div>
                            <strong>Multiselect Checkbox with Text / Item Pricing Matrix:</strong>
                            Add structured items below to define Paid/Free options, per-unit price, and Govt Subsidy % (0–100%), or type comma-separated plain options below.
                        </div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" class="form-check-input js-billable-matrix-toggle"
                               name="fields[{{ $index }}][is_billable_items_field]" value="1"
                               @checked($isBillableMatrix)>
                        <label class="form-check-label small fw-bold text-primary">
                            Use as Billable / Stock Item Matrix
                        </label>
                    </div>
                </div>

                <div class="border rounded p-2 bg-white mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-dark">
                            <i class="fa-solid fa-tags text-success me-1"></i> Configured Paid &amp; Free Item Options (Optional)
                        </span>
                        <button type="button" class="btn btn-xs btn-outline-success btn-sm py-0 px-2 js-add-option-config">
                            <i class="fa-solid fa-plus me-1"></i> Add Item Option
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 js-option-configs-table" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:140px;">Item Name (EN)</th>
                                    <th style="min-width:130px;">Urdu Label (اردو)</th>
                                    <th style="min-width:100px;">Billing</th>
                                    <th style="min-width:105px;">Unit Rate (PKR)</th>
                                    <th style="min-width:105px;">Govt Share %</th>
                                    <th style="min-width:115px;">Input Type</th>
                                    <th style="min-width:85px;">Unit</th>
                                    <th style="width:42px;"></th>
                                </tr>
                            </thead>
                            <tbody class="js-option-configs-body">
                                @foreach($optionConfigs as $oIdx => $optCfg)
                                    @php
                                        $optPricing = ($optCfg['pricing_mode'] ?? 'free') === 'paid' ? 'paid' : 'free';
                                    @endphp
                                    <tr class="js-option-config-row">
                                        <td>
                                            <input type="hidden" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][key]" value="{{ $optCfg['key'] ?? '' }}">
                                            <input type="text" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][label]" class="form-control form-control-sm" value="{{ $optCfg['label'] ?? '' }}" placeholder="e.g. Olive Trees">
                                        </td>
                                        <td>
                                            <input type="text" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][label_ur]" class="form-control form-control-sm urdu-font" dir="rtl" value="{{ $optCfg['label_ur'] ?? '' }}" placeholder="مثلاً زیتون">
                                        </td>
                                        <td>
                                            <select name="fields[{{ $index }}][option_configs][{{ $oIdx }}][pricing_mode]" class="form-select form-select-sm js-opt-pricing-mode">
                                                <option value="free" @selected($optPricing === 'free')>Free</option>
                                                <option value="paid" @selected($optPricing === 'paid')>Paid</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][unit_price]" class="form-control form-control-sm js-opt-unit-price" value="{{ $optPricing === 'paid' ? ($optCfg['unit_price'] ?? 0) : 0 }}" {{ $optPricing === 'free' ? 'readonly' : '' }}>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][govt_share_pct]" class="form-control form-control-sm js-opt-govt-share" value="{{ $optCfg['govt_share_pct'] ?? '' }}" placeholder="Template %" {{ $optPricing === 'free' ? 'disabled' : '' }}>
                                        </td>
                                        <td>
                                            <select name="fields[{{ $index }}][option_configs][{{ $oIdx }}][input_mode]" class="form-select form-select-sm">
                                                <option value="numeric_qty" @selected(($optCfg['input_mode'] ?? 'numeric_qty') === 'numeric_qty')>Numeric Qty</option>
                                                <option value="text" @selected(($optCfg['input_mode'] ?? 'text') === 'text')>Text Note</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="fields[{{ $index }}][option_configs][{{ $oIdx }}][unit_label]" class="form-control form-control-sm" value="{{ $optCfg['unit_label'] ?? 'Trees' }}" placeholder="Trees">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 js-remove-option-config" title="Remove Option">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-2">
                <label class="form-label small text-muted mb-1 js-options-label">
                    {{ ($field?->field_type ?? '') === 'checkbox_with_text' ? 'Plain Checkbox Options (comma or newline separated — overridden if Item Matrix rows are defined above)' : 'Options (comma or newline separated)' }}
                </label>
                <textarea name="fields[{{ $index }}][options]" class="form-control form-control-sm js-options-input" rows="2" placeholder="{{ ($field?->field_type ?? '') === 'checkbox_with_text' ? 'Commercial Bank, ZTBL, Microfinance, Informal / Other' : 'Option 1, Option 2' }}">{{ $options }}</textarea>
            </div>
        @endunless
    </div>
</div>
