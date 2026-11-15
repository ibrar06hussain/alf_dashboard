@extends('layouts.admin')

@section('title', 'Form Builder')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    .urdu-font {
        font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, sans-serif;
    }
</style>
@endpush

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Form Builder',
    'subtitle' => $scheme->title.' · Form v'.$form->version.($form->is_published ? ' · Published' : ' · Draft'),
    'icon' => 'fa-solid fa-gear',
    'actions' => [
        ['label' => 'Back to Scheme', 'url' => route('admin.schemes.show', $scheme), 'class' => 'btn btn-light'],
    ],
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Fields', 'value' => $form->fields->count(), 'icon' => 'fa-solid fa-list', 'tone' => 'green'],
    ['label' => 'Documents', 'value' => $form->documents->count(), 'icon' => 'fa-solid fa-paperclip', 'tone' => 'blue'],
    ['label' => 'Version', 'value' => $form->version, 'icon' => 'fa-solid fa-code-branch', 'tone' => 'slate'],
    ['label' => 'Status', 'value' => $form->is_published ? 'Published' : 'Draft', 'icon' => 'fa-solid fa-circle-check', 'tone' => $form->is_published ? 'teal' : 'amber'],
]])

<form method="POST" action="{{ route('admin.form-builder.update', $scheme) }}" id="form-builder-form">
    @csrf @method('PUT')

    <div class="card alf-panel mb-3">
        <div class="card-header">Form settings</div>
        <div class="card-body alf-form-grid">
            <div class="mb-3">
                <label class="form-label">Form title</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $form->title) }}" required>
            </div>
            <p class="text-muted mb-0">Core fields are locked (key/type/mandatory). You may reorder them and edit placeholder/help text. Custom fields can be added, edited, and removed.</p>
        </div>
    </div>

    {{-- Group / Multi-Member Application Configuration --}}
    @php
        $isGroupApp = (bool) old('is_group_application', $form->is_group_application ?? false);
        $minMembers = old('min_group_members', $form->min_group_members ?: 1);
        $maxMembers = old('max_group_members', $form->max_group_members ?: 10);
        $groupTitleEn = old('group_section_title', $form->group_section_title ?: 'Group Members Details (Other than Group Leader)');
        $groupTitleUr = old('group_section_title_ur', $form->group_section_title_ur ?: 'گروپ کے دیگر ممبران کی تفصیلات (گروپ لیڈر کے علاوہ)');
        $memberCustomFields = old('member_custom_fields', $form->member_custom_fields_json ?? []);
    @endphp
    <div class="card alf-panel mb-3 border-primary-subtle">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <i class="fa-solid fa-users-gear text-primary me-2"></i>
                <strong>Group / Multi-Member Application Settings</strong>
                <span class="text-success ms-2 urdu-font" dir="rtl">(گروپ درخواست و ممبران کی ترتیبات)</span>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="is_group_application" value="1" id="is_group_application" @checked($isGroupApp)>
                <label class="form-check-label fw-bold text-primary" for="is_group_application">
                    Enable Group Application (1 Group Leader + Multiple Members)
                </label>
            </div>
        </div>
        <div class="card-body {{ $isGroupApp ? '' : 'd-none' }}" id="group-settings-panel">
            <div class="alert alert-info py-2 small mb-3">
                <i class="fa-solid fa-circle-info me-1"></i>
                When enabled, the primary applicant section acts as the <strong>Group Leader (گروپ لیڈر)</strong>, and a dynamic bilingual <strong>Group Members</strong> repeater is added to the form. Every member is required to have a <strong>unique CNIC</strong> and a <strong>unique Mobile Number</strong> different from the Group Leader and other members.
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Min Additional Members <span class="text-danger">*</span></label>
                    <input type="number" name="min_group_members" class="form-control form-control-sm" min="1" max="50" value="{{ $minMembers }}">
                    <div class="form-text small">Besides the Group Leader</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Max Additional Members <span class="text-danger">*</span></label>
                    <input type="number" name="max_group_members" class="form-control form-control-sm" min="1" max="100" value="{{ $maxMembers }}">
                    <div class="form-text small">Maximum members allowed</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Section Heading (English)</label>
                    <input type="text" name="group_section_title" class="form-control form-control-sm" value="{{ $groupTitleEn }}" placeholder="Group Members Details">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-success">سیکشن کا عنوان (اردو / Urdu)</label>
                    <input type="text" name="group_section_title_ur" class="form-control form-control-sm urdu-font" dir="rtl" value="{{ $groupTitleUr }}" placeholder="گروپ کے دیگر ممبران کی تفصیلات">
                </div>
            </div>

            <div class="mb-3 p-2 rounded bg-light border small">
                <strong class="text-dark d-block mb-1">Built-in Bilingual Fields Captured for Each Group Member:</strong>
                <span class="badge bg-secondary me-1">Full Name / پورا نام (Required)</span>
                <span class="badge bg-secondary me-1">Father/Husband Name / والد یا شوہر کا نام (Required)</span>
                <span class="badge bg-primary me-1">CNIC (13 Digits, Unique per Member) / شناختی کارڈ نمبر</span>
                <span class="badge bg-primary me-1">Mobile No (Unique per Member) / موبائل نمبر</span>
                <span class="badge bg-secondary me-1">Village / Address / گاؤں یا پتہ</span>
                <span class="badge bg-secondary me-1">Land Area (Kanals) / رقبہ (کنال)</span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label small fw-bold mb-0">
                    Additional Custom Fields per Group Member (Optional — English &amp; Urdu)
                </label>
                <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" id="add-member-field-btn">
                    <i class="fa-solid fa-plus me-1"></i> Add Custom Member Field
                </button>
            </div>

            <div id="member-custom-fields-list">
                @foreach($memberCustomFields as $mIdx => $mField)
                    <div class="member-custom-field-row border rounded p-2 mb-2 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Field Key</label>
                                <input type="text" name="member_custom_fields[{{ $mIdx }}][key]" class="form-control form-control-sm font-monospace"
                                    value="{{ $mField['key'] ?? '' }}" placeholder="e.g. share_pct">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Label (English)</label>
                                <input type="text" name="member_custom_fields[{{ $mIdx }}][label]" class="form-control form-control-sm"
                                    value="{{ $mField['label'] ?? '' }}" placeholder="e.g. Role / Share in Group">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-success mb-1">لیبل (اردو / Urdu)</label>
                                <input type="text" name="member_custom_fields[{{ $mIdx }}][label_ur]" class="form-control form-control-sm urdu-font"
                                    dir="rtl" value="{{ $mField['label_ur'] ?? '' }}" placeholder="مثلاً گروپ میں حصہ">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Type</label>
                                <select name="member_custom_fields[{{ $mIdx }}][type]" class="form-select form-select-sm">
                                    @foreach(['text' => 'Text', 'number' => 'Number', 'select' => 'Dropdown', 'textarea' => 'Textarea'] as $tKey => $tLabel)
                                        <option value="{{ $tKey }}" @selected(($mField['type'] ?? 'text') === $tKey)>{{ $tLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-center justify-content-between">
                                <div class="form-check mb-1">
                                    <input type="checkbox" class="form-check-input" name="member_custom_fields[{{ $mIdx }}][required]" value="1"
                                        @checked(!empty($mField['required']))>
                                    <label class="form-check-label small">Req</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-member-field">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                            <div class="col-12">
                                <input type="text" name="member_custom_fields[{{ $mIdx }}][options]" class="form-control form-control-sm"
                                    value="{{ is_array($mField['options'] ?? null) ? implode(', ', $mField['options']) : ($mField['options'] ?? '') }}"
                                    placeholder="Dropdown options (comma separated, if Type is Dropdown)">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card alf-panel mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Fields</span>
            <button type="button" class="btn btn-sm btn-primary" id="add-field-btn">Add Field</button>
        </div>
        <div class="card-body">
            <div id="fields-list">
                @foreach($form->fields as $index => $field)
                    @include('admin.form-builder._field-row', ['field' => $field, 'index' => $index, 'fieldTypes' => $fieldTypes])
                @endforeach
            </div>
        </div>
    </div>

    <div class="card alf-panel mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Required Documents</span>
            <button type="button" class="btn btn-sm btn-primary" id="add-doc-btn">Add Document</button>
        </div>
        <div class="card-body">
            <div id="documents-list">
                @foreach($form->documents as $index => $document)
                    @include('admin.form-builder._document-row', ['document' => $document, 'index' => $index])
                @endforeach
            </div>
        </div>
    </div>

    <div class="mb-4">
        <input type="hidden" name="publish" id="publish_flag" value="0">
        <button type="submit" class="btn btn-primary" data-publish="0">Save Draft</button>
        <button type="submit" class="btn btn-success" data-publish="1">Save & Publish</button>
    </div>
</form>

<template id="field-template">
    @include('admin.form-builder._field-row', ['field' => null, 'index' => '__INDEX__', 'fieldTypes' => $fieldTypes])
</template>
<template id="document-template">
    @include('admin.form-builder._document-row', ['document' => null, 'index' => '__INDEX__'])
</template>
<template id="member-field-template">
    <div class="member-custom-field-row border rounded p-2 mb-2 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Field Key</label>
                <input type="text" name="member_custom_fields[__MINDEX__][key]" class="form-control form-control-sm font-monospace" placeholder="e.g. share_pct">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Label (English)</label>
                <input type="text" name="member_custom_fields[__MINDEX__][label]" class="form-control form-control-sm" placeholder="e.g. Role / Share in Group">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-success mb-1">لیبل (اردو / Urdu)</label>
                <input type="text" name="member_custom_fields[__MINDEX__][label_ur]" class="form-control form-control-sm urdu-font" dir="rtl" placeholder="مثلاً گروپ میں حصہ">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select name="member_custom_fields[__MINDEX__][type]" class="form-select form-select-sm">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="select">Dropdown</option>
                    <option value="textarea">Textarea</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center justify-content-between">
                <div class="form-check mb-1">
                    <input type="checkbox" class="form-check-input" name="member_custom_fields[__MINDEX__][required]" value="1">
                    <label class="form-check-label small">Req</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-member-field">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
            <div class="col-12">
                <input type="text" name="member_custom_fields[__MINDEX__][options]" class="form-control form-control-sm" placeholder="Dropdown options (comma separated, if Type is Dropdown)">
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script src="{{ asset('admin-assets/js/sortable.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fieldsList = document.getElementById('fields-list');
    const docsList = document.getElementById('documents-list');
    const form = document.getElementById('form-builder-form');

    form.addEventListener('submit', function (e) {
        const submitter = e.submitter;
        const flag = document.getElementById('publish_flag');
        if (submitter && submitter.dataset.publish !== undefined) {
            flag.value = submitter.dataset.publish;
        }
    });

    function reindex(container, prefix) {
        [...container.children].forEach((row, i) => {
            row.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(new RegExp(prefix + '\\[(\\d+|__INDEX__)\\]'), prefix + '[' + i + ']');
            });
            const order = row.querySelector('[data-order]');
            if (order) order.value = i + 1;
        });
    }

    if (window.Sortable) {
        Sortable.create(fieldsList, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: () => reindex(fieldsList, 'fields')
        });
        Sortable.create(docsList, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: () => reindex(docsList, 'documents')
        });
    }

    document.getElementById('add-field-btn').addEventListener('click', function () {
        const html = document.getElementById('field-template').innerHTML.replaceAll('__INDEX__', fieldsList.children.length);
        fieldsList.insertAdjacentHTML('beforeend', html);
        reindex(fieldsList, 'fields');
    });

    document.getElementById('add-doc-btn').addEventListener('click', function () {
        const html = document.getElementById('document-template').innerHTML.replaceAll('__INDEX__', docsList.children.length);
        docsList.insertAdjacentHTML('beforeend', html);
        reindex(docsList, 'documents');
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.remove-field')) {
            const row = e.target.closest('.field-row');
            if (row && !row.dataset.core) {
                row.remove();
                reindex(fieldsList, 'fields');
            }
        }
        if (e.target.closest('.remove-doc')) {
            e.target.closest('.document-row')?.remove();
            reindex(docsList, 'documents');
        }
        if (e.target.closest('.remove-member-field')) {
            e.target.closest('.member-custom-field-row')?.remove();
        }
    });

    const groupToggle = document.getElementById('is_group_application');
    const groupPanel = document.getElementById('group-settings-panel');
    const addMemberFieldBtn = document.getElementById('add-member-field-btn');
    const memberFieldsList = document.getElementById('member-custom-fields-list');

    if (groupToggle && groupPanel) {
        groupToggle.addEventListener('change', function () {
            groupPanel.classList.toggle('d-none', !groupToggle.checked);
        });
    }

    if (addMemberFieldBtn && memberFieldsList) {
        addMemberFieldBtn.addEventListener('click', function () {
            const idx = memberFieldsList.children.length;
            const html = document.getElementById('member-field-template').innerHTML.replaceAll('__MINDEX__', idx);
            memberFieldsList.insertAdjacentHTML('beforeend', html);
        });
    }

    fieldsList.addEventListener('change', function (e) {
        if (e.target && e.target.name && e.target.name.endsWith('[field_type]')) {
            const row = e.target.closest('.field-row');
            if (!row) return;
            const infoBox = row.querySelector('.js-checkbox-text-info');
            const optionsLabel = row.querySelector('.js-options-label');
            const optionsInput = row.querySelector('.js-options-input');
            const isCbText = e.target.value === 'checkbox_with_text';

            if (infoBox) {
                if (isCbText) {
                    infoBox.classList.remove('d-none');
                } else {
                    infoBox.classList.add('d-none');
                }
            }

            if (optionsLabel && optionsInput) {
                if (isCbText) {
                    optionsLabel.textContent = 'Checkbox Options (comma or newline separated — each option gets a dynamic text field)';
                    optionsInput.placeholder = 'Commercial Bank, ZTBL, Microfinance, Informal / Other';
                } else {
                    optionsLabel.textContent = 'Options (comma or newline separated)';
                    optionsInput.placeholder = 'Yes, No';
                }
            }
        }
    });
});
</script>
@endpush
