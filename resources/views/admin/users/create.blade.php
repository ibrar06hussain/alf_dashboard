@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Add User',
    'subtitle' => 'Create an admin account with role and district / department scope.',
    'icon' => 'fa-solid fa-user-plus',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.users.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Account details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Create User</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
