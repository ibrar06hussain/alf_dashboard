@extends('layouts.admin')

@section('title', 'Add Carousel Slide')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Add Carousel Slide',
    'subtitle' => 'Appears in the homepage hero when Active.',
    'icon' => 'fa-solid fa-plus',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.carousel.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Slide details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.carousel.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.carousel._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Save Slide</button>
                <a href="{{ route('admin.carousel.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
