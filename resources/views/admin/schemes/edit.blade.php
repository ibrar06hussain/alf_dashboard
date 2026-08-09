@extends('layouts.admin')

@section('title', 'Edit Scheme')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Edit '.$scheme->typeLabel().' Scheme',
    'subtitle' => $scheme->title,
    'icon' => 'fa-solid fa-pen',
    'actions' => array_values(array_filter([
        $scheme->type === 'running'
            ? ['label' => 'Form Builder', 'url' => route('admin.form-builder.edit', $scheme), 'class' => 'btn btn-info']
            : null,
        ['label' => 'View', 'url' => route('admin.schemes.show', $scheme), 'class' => 'btn btn-light'],
    ])),
])

<div class="card alf-panel mb-3">
    <div class="card-header">Scheme details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.schemes.update', $scheme) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.schemes._form')
            <div class="alf-form-actions">
                <button type="submit" class="btn btn-primary" id="scheme-submit-btn" data-running-label="Update Scheme">Update Scheme</button>
                <a href="{{ route('admin.schemes.index', $scheme->type === 'proposed' ? ['type' => 'proposed'] : []) }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
