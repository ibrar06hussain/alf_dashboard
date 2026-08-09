@extends('layouts.admin')

@section('title', 'Add Department')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Add Department',
    'subtitle' => 'Create a department profile for the public portal and scheme ownership.',
    'icon' => 'fa-solid fa-plus',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.departments.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Department details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.departments.store') }}">
            @csrf
            @include('admin.departments._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Save Department</button>
                <a href="{{ route('admin.departments.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
