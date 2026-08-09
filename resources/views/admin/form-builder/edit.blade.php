@extends('layouts.admin')

@section('title', 'Form Builder')

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
    });
});
</script>
@endpush
