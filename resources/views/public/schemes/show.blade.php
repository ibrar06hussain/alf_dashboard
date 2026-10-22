@extends('layouts.public')

@section('title', $scheme->t("title"))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ $scheme->bannerUrl(6) }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ $scheme->t("title") }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('schemes.index') }}" class="text-white">{{ __('portal.schemes') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.details') }}</li>
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
            <div class="col-lg-8 mb-4">
                <div class="mb-3">
                    <span class="badge {{ $scheme->type === 'running' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $scheme->typeLabel() }}</span>
                    <span class="badge bg-secondary">{{ $scheme->scopeLabel() }}</span>
                    <span class="badge bg-info text-dark">{{ $scheme->department?->t("name") }}</span>
                </div>

                <h3 class="mb-3">{{ $scheme->t("title") }}</h3>

                @if($scheme->featured_image)
                    <div class="alf-media-detail mb-4 rounded-3 overflow-hidden">
                        <img src="{{ $scheme->imageUrl() }}"
                             alt="{{ $scheme->t("title") }}"
                             class="alf-media-detail__img"
                             data-alf-fit="img"
                             data-alf-w="{{ \App\Support\ImageSlots::get('scheme_detail')['width'] }}"
                             data-alf-h="{{ \App\Support\ImageSlots::get('scheme_detail')['height'] }}">
                    </div>
                @endif

                @if($scheme->t("description"))
                    <h5>{{ __('portal.description') }}</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->t("description"))) !!}</div>
                @endif

                @if($scheme->t("eligibility_criteria"))
                    <h5>{{ __('portal.eligibility_criteria') }}</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->t("eligibility_criteria"))) !!}</div>
                @endif

                @if($scheme->t("terms_conditions"))
                    <h5>{{ __('portal.terms_conditions') }}</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->t("terms_conditions"))) !!}</div>
                @endif

                @if($scheme->districts->isNotEmpty())
                    <h5>{{ __('portal.covered_districts') }}</h5>
                    <ul class="mb-4">
                        @foreach($scheme->districts as $district)
                            <li>{{ $district->t("name") }} @if($district->pivot->quota) — {{ __('portal.quota') }}: {{ $district->pivot->quota }} @endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <div class="border rounded-3 p-4 bg-light mb-4">
                    <h5 class="mb-3">{{ __('portal.scheme_info') }}</h5>
                    <p class="mb-2"><strong>{{ __('portal.status') }}:</strong> {{ ucfirst($scheme->status) }}</p>
                    @if($scheme->budget)
                        <p class="mb-2"><strong>{{ __('portal.budget') }}:</strong> PKR {{ number_format($scheme->budget, 0) }}</p>
                    @endif
                    @if($scheme->start_date)
                        <p class="mb-2"><strong>{{ __('portal.start_date') }}:</strong> {{ $scheme->start_date->format('d M Y') }}</p>
                    @endif
                    @if($scheme->last_date)
                        @php $daysLeft = now()->startOfDay()->diffInDays($scheme->last_date->copy()->startOfDay(), false); @endphp
                        <p class="mb-2"><strong>{{ __('portal.last_date') }}:</strong> {{ $scheme->last_date->format('d M Y') }}</p>
                        @if($scheme->isOpenForApplications() && $daysLeft >= 0)
                            <p class="mb-2"><strong>{{ __('portal.time_left') }}:</strong> {{ __('portal.day_s', ['count' => (int) $daysLeft]) }}</p>
                        @endif
                    @endif

                    @if($scheme->isOpenForApplications())
                        <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn famie-btn alf-btn-apply alf-btn-apply-pulse w-100 mt-3 d-flex align-items-center justify-content-center">
                            <span class="alf-pulse-beacon" aria-hidden="true">
                                <span class="alf-beacon-ping"></span>
                                <span class="alf-beacon-dot"></span>
                            </span>
                            <i class="fa-solid fa-paper-plane me-2"></i> {{ __('portal.apply_now') }}
                        </a>
                    @elseif($scheme->type === 'proposed')
                        <p class="small text-muted mt-3 mb-0">{{ __('portal.proposed_notice') }}</p>
                    @else
                        <div class="alert alert-warning mt-3 mb-0">{{ __('portal.applications_closed') }}</div>
                    @endif
                </div>

                @if($scheme->type === 'proposed' || ! $scheme->isOpenForApplications())
                    @include('public.schemes.interest', ['scheme' => $scheme, 'districts' => $districts])
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
