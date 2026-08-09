@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Edit User',
    'subtitle' => $user->email,
    'icon' => 'fa-solid fa-user-pen',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.users.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Account details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')
            @include('admin.users._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Update User</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
