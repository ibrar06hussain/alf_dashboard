@extends('layouts.public')

@section('title', __('portal.about_title').' '.\App\Models\SiteSetting::text('site_name'))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('about_breadcrumb', 'frontend/img/bg-img/5.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ __('portal.about_us') }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.about') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="alf-media-about rounded-3 overflow-hidden">
                    <img src="{{ \App\Support\Media::site('about_side_image', 'frontend/img/bg-img/2.jpg') }}"
                         class="alf-media-about__img"
                         alt="{{ \App\Models\SiteSetting::text('site_name') }}"
                         data-alf-fit="img"
                         data-alf-w="{{ \App\Support\ImageSlots::get('about_side')['width'] }}"
                         data-alf-h="{{ \App\Support\ImageSlots::get('about_side')['height'] }}">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-heading">
                    <p>{{ \App\Models\SiteSetting::text('about_eyebrow') }}</p>
                    <h2>{{ \App\Models\SiteSetting::text('about_heading') }}</h2>
                </div>
                <p>{{ \App\Models\SiteSetting::text('about_p1') }}</p>
                <p>{{ \App\Models\SiteSetting::text('about_p2') }}</p>
            </div>
        </div>

        <div class="row">
            @foreach($departments as $department)
                <div class="col-md-4 mb-4">
                    <div class="border rounded-3 p-4 h-100">
                        <h5>{{ $department->t("name") }}</h5>
                        <p class="small text-muted">{{ \Illuminate\Support\Str::limit($department->t("mission"), 160) }}</p>
                        <a href="{{ route('departments.show', $department->slug) }}">{{ __('portal.learn_more') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
