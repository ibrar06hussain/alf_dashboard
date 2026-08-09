<div class="document-row border rounded p-3 mb-3">
    <div class="d-flex justify-content-between mb-2">
        <strong class="drag-handle" style="cursor:grab;"><i class="fa-solid fa-bars me-2"></i>Document</strong>
        <button type="button" class="btn btn-sm btn-outline-danger remove-doc">Remove</button>
    </div>
    <input type="hidden" name="documents[{{ $index }}][id]" value="{{ $document?->id ?? '' }}">
    <input type="hidden" name="documents[{{ $index }}][display_order]" data-order value="{{ $document?->display_order ?? ($index === '__INDEX__' ? 0 : $index + 1) }}">
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="form-label">Document name</label>
            <input type="text" name="documents[{{ $index }}][document_name]" class="form-control" value="{{ $document?->document_name ?? '' }}" required>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label">Allowed extensions</label>
            <input type="text" name="documents[{{ $index }}][allowed_extensions]" class="form-control" value="{{ $document?->allowed_extensions ?? 'pdf,jpg,jpeg,png' }}">
        </div>
        <div class="col-md-2 mb-2">
            <label class="form-label">Max MB</label>
            <input type="number" name="documents[{{ $index }}][max_size_mb]" class="form-control" value="{{ $document?->max_size_mb ?? 5 }}" min="1" max="50">
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label d-block">Mandatory</label>
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" name="documents[{{ $index }}][is_mandatory]" value="1"
                    {{ ($document?->is_mandatory ?? true) ? 'checked' : '' }}>
                <label class="form-check-label">Required</label>
            </div>
        </div>
    </div>
</div>
