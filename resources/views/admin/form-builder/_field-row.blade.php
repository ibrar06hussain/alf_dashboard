@php
    $isCore = $field?->is_core ?? false;
    $options = is_array($field?->options_json ?? null) ? implode(', ', $field->options_json) : '';
@endphp
<div class="field-row border rounded p-3 mb-3 bg-light" @if($isCore) data-core="1" @endif>
    <div class="d-flex justify-content-between mb-2">
        <strong class="drag-handle" style="cursor:grab;">
            <i class="fa-solid fa-bars me-2"></i>
            {{ $isCore ? 'Core Field' : 'Custom Field' }}
        </strong>
        @unless($isCore)
            <button type="button" class="btn btn-sm btn-outline-danger remove-field">Remove</button>
        @endunless
    </div>
    <input type="hidden" name="fields[{{ $index }}][id]" value="{{ $field?->id ?? '' }}">
    <input type="hidden" name="fields[{{ $index }}][display_order]" data-order value="{{ $field?->display_order ?? ($index === '__INDEX__' ? 0 : $index + 1) }}">
    <div class="row">
        <div class="col-md-3 mb-2">
            <label class="form-label">Key</label>
            <input type="text" name="fields[{{ $index }}][field_key]" class="form-control"
                   value="{{ $field?->field_key ?? '' }}" {{ $isCore ? 'readonly' : 'required' }}>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label">Label</label>
            <input type="text" name="fields[{{ $index }}][field_label]" class="form-control"
                   value="{{ $field?->field_label ?? '' }}" {{ $isCore ? 'readonly' : 'required' }}>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label">Type</label>
            <select name="fields[{{ $index }}][field_type]" class="form-control" {{ $isCore ? 'disabled' : '' }}>
                @foreach($fieldTypes as $type)
                    <option value="{{ $type }}" @selected(($field?->field_type ?? 'text') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @if($isCore)
                <input type="hidden" name="fields[{{ $index }}][field_type]" value="{{ $field?->field_type }}">
            @endif
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label d-block">Mandatory</label>
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" name="fields[{{ $index }}][is_mandatory]" value="1"
                    {{ ($field?->is_mandatory ?? false) || $isCore ? 'checked' : '' }} {{ $isCore ? 'disabled' : '' }}>
                @if($isCore)
                    <input type="hidden" name="fields[{{ $index }}][is_mandatory]" value="1">
                @endif
                <label class="form-check-label">Required</label>
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label">Placeholder</label>
            <input type="text" name="fields[{{ $index }}][placeholder]" class="form-control" value="{{ $field?->placeholder ?? '' }}">
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label">Help text</label>
            <input type="text" name="fields[{{ $index }}][help_text]" class="form-control" value="{{ $field?->help_text ?? '' }}">
        </div>
        @unless($isCore)
            <div class="col-md-12 mb-2">
                <label class="form-label">Options (comma or newline separated)</label>
                <textarea name="fields[{{ $index }}][options]" class="form-control" rows="2" placeholder="Yes, No">{{ $options }}</textarea>
            </div>
        @endunless
    </div>
</div>
