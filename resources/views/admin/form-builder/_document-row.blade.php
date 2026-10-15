<div class="document-row border rounded p-3 mb-3 bg-light shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
        <strong class="drag-handle text-dark" style="cursor:grab;">
            <i class="fa-solid fa-bars me-2 text-muted"></i>
            <span class="badge bg-info-subtle text-info-emphasis me-1">Document</span>
        </strong>
        <button type="button" class="btn btn-sm btn-outline-danger remove-doc">
            <i class="fa-solid fa-trash-can me-1"></i> Remove
        </button>
    </div>
    <input type="hidden" name="documents[{{ $index }}][id]" value="{{ $document?->id ?? '' }}">
    <input type="hidden" name="documents[{{ $index }}][display_order]" data-order value="{{ $document?->display_order ?? ($index === '__INDEX__' ? 0 : $index + 1) }}">
    <div class="row g-2">
        <div class="col-12 col-md-6 col-lg-4 mb-2">
            <label class="form-label small fw-semibold">Document Name (English) <span class="text-danger">*</span></label>
            <input type="text" name="documents[{{ $index }}][document_name]" class="form-control form-control-sm" value="{{ $document?->document_name ?? '' }}" placeholder="e.g. CNIC Front & Back" required>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-2">
            <label class="form-label small fw-semibold text-success">
                دستاویز کا نام (اردو ترجمہ) <span class="badge bg-success-subtle text-success">Urdu</span>
            </label>
            <input type="text" name="documents[{{ $index }}][document_name_ur]" class="form-control form-control-sm" dir="rtl"
                   value="{{ $document?->document_name_ur ?? ($document ? $document->getUrduName() : '') }}"
                   placeholder="مثلاً شناختی کارڈ کی کاپی"
                   style="font-family: 'Noto Nastaliq Urdu', Tahoma, sans-serif;">
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <label class="form-label small fw-semibold text-muted">Allowed Extensions</label>
            <input type="text" name="documents[{{ $index }}][allowed_extensions]" class="form-control form-control-sm" value="{{ $document?->allowed_extensions ?? 'pdf,jpg,jpeg,png' }}">
        </div>
        <div class="col-3 col-md-4 col-lg-1 mb-2">
            <label class="form-label small fw-semibold text-muted">Max MB</label>
            <input type="number" name="documents[{{ $index }}][max_size_mb]" class="form-control form-control-sm" value="{{ $document?->max_size_mb ?? 5 }}" min="1" max="50">
        </div>
        <div class="col-3 col-md-4 col-lg-1 mb-2">
            <label class="form-label small fw-semibold d-block text-muted">Mandatory</label>
            <div class="form-check mt-1">
                <input type="checkbox" class="form-check-input" name="documents[{{ $index }}][is_mandatory]" value="1"
                    {{ ($document?->is_mandatory ?? true) ? 'checked' : '' }}>
                <label class="form-check-label small">Req</label>
            </div>
        </div>
    </div>
</div>

