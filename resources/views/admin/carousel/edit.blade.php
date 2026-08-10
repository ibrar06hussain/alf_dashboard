@extends('layouts.admin')

@section('title', 'Edit Carousel Slide')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Edit Carousel Slide',
    'subtitle' => $slide->title,
    'icon' => 'fa-solid fa-pen',
    'actions' => [
        ['label' => 'Back', 'url' => route('admin.carousel.index'), 'class' => 'btn btn-light'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Slide details</div>
    <div class="card-body alf-form-grid">
        <form method="POST" action="{{ route('admin.carousel.update', $slide) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.carousel._form')
            <div class="alf-form-actions">
                <button class="btn btn-primary">Update Slide</button>
                <a href="{{ route('admin.carousel.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
