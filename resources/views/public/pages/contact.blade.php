@extends('layouts.public')

@section('title', __('portal.contact_title'))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('contact_breadcrumb', 'frontend/img/bg-img/11.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ __('portal.contact_title') }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.contact') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="section-heading">
                    <p>{{ __('portal.get_in_touch') }}</p>
                    <h2>{{ \App\Models\SiteSetting::text('contact_heading') }}</h2>
                </div>
                <p>{{ \App\Models\SiteSetting::text('contact_intro') }}</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fa-solid fa-map-pin me-2"></i> {{ \App\Models\SiteSetting::text('contact_address') }}</li>
                    @if($email = \App\Models\SiteSetting::text('contact_email'))
                        <li class="mb-2"><i class="fa-solid fa-envelope me-2"></i> <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                    @endif
                    @if($phone = \App\Models\SiteSetting::text('contact_phone'))
                        <li class="mb-2"><i class="fa-solid fa-phone me-2"></i> <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></li>
                    @endif
                    <li class="mb-2"><i class="fa-solid fa-globe me-2"></i> {{ \App\Models\SiteSetting::text('organization_name') }}</li>
                </ul>
            </div>
            <div class="col-lg-7">
                <div class="border rounded-3 p-4 bg-white shadow-sm">
                    <form method="post" action="{{ route('contact.submit') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('portal.name') }}</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('portal.email') }}</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('portal.subject') }}</label>
                                <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required>
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('portal.message') }}</label>
                                <textarea name="message" rows="5" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn famie-btn">{{ __('portal.send_message') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
