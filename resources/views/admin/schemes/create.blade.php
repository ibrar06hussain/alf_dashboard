@extends('layouts.admin')

@section('title', ($defaultType ?? 'running') === 'proposed' ? 'Add Proposed Scheme' : 'Add Scheme')

@section('content')
@include('admin.partials.page-hero', [
    'title' => ($defaultType ?? 'running') === 'proposed' ? 'Add Proposed Scheme (CM)' : 'Add Scheme',
    'subtitle' => ($defaultType ?? 'running') === 'proposed'
        ? 'Published proposed schemes appear on the public CM section for express interest.'
        : 'On going schemes can collect online applications via the form builder.',
    'icon' => 'fa-solid fa-plus',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.schemes.index', ($defaultType ?? '') === 'proposed' ? ['type' => 'proposed'] : []), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Scheme details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.schemes.store') }}" enctype="multipart/form-data">
            @csrf
            @php $quotas = collect(); @endphp
            @include('admin.schemes._form')
            <div class="alf-form-actions">
                <button type="submit" class="btn btn-primary" id="scheme-submit-btn"
                        data-running-label="Create & open Form Builder">
                    {{ ($defaultType ?? 'running') === 'proposed' ? 'Save Proposed Scheme' : 'Create & open Form Builder' }}
                </button>
                <a href="{{ route('admin.schemes.index', ($defaultType ?? '') === 'proposed' ? ['type' => 'proposed'] : []) }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
