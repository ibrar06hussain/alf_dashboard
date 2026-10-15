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
            <div class="col-12 mt-2 js-checkbox-text-info {{ ($field?->field_type ?? '') === 'checkbox_with_text' ? '' : 'd-none' }}">
                <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-info text-info fs-5"></i>
                    <div>
                        <strong>Multiselect Checkbox with Text Field:</strong>
                        Each option listed below will appear as an independent checkbox. When the citizen checks any option, a text field dynamically expands for that specific selected option so they can enter its details/value.
                    </div>
                </div>
            </div>

            <div class="col-12 mt-2">
                <label class="form-label small text-muted mb-1 js-options-label">
                    {{ ($field?->field_type ?? '') === 'checkbox_with_text' ? 'Checkbox Options (comma or newline separated — each option gets a dynamic text field)' : 'Options (comma or newline separated)' }}
                </label>
                <textarea name="fields[{{ $index }}][options]" class="form-control form-control-sm js-options-input" rows="2" placeholder="{{ ($field?->field_type ?? '') === 'checkbox_with_text' ? 'Commercial Bank, ZTBL, Microfinance, Informal / Other' : 'Option 1, Option 2' }}">{{ $options }}</textarea>
            </div>
        @endunless
    </div>
</div>
