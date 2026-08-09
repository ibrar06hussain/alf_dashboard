@extends('layouts.admin')

@section('title', 'Edit Stat')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Edit Stat',
    'subtitle' => $stat->stat_label,
    'icon' => 'fa-solid fa-pen',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.stats.index', ['department_id' => $stat->department_id]), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Stat details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.stats.update', $stat) }}">
            @csrf @method('PUT')
            @include('admin.stats._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Update Stat</button>
                <a href="{{ route('admin.stats.index', ['department_id' => $stat->department_id]) }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
