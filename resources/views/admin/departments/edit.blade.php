@extends('layouts.admin')

@section('title', 'Edit Department')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Edit Department',
    'subtitle' => $department->name,
    'icon' => 'fa-solid fa-pen',
    'actions' => [
        ['label' => 'View', 'url' => route('admin.departments.show', $department), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Department details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.departments.update', $department) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.departments._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Update Department</button>
                <a href="{{ route('admin.departments.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
