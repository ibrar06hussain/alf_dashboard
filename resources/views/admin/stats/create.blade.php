@extends('layouts.admin')

@section('title', 'Add Stat')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Add Department Stat',
    'subtitle' => 'Manual or auto-aggregated metric for the public portal.',
    'icon' => 'fa-solid fa-plus',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.stats.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Stat details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.stats.store') }}">
            @csrf
            @include('admin.stats._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Save Stat</button>
                <a href="{{ route('admin.stats.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
