@extends('layouts.public')

@section('title', __('portal.schemes'))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('schemes_breadcrumb', 'frontend/img/bg-img/8.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ __('portal.schemes') }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.schemes') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <form method="get" action="{{ route('schemes.index') }}" class="row g-3 mb-5 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('portal.department') }}</label>
                <select name="department" class="form-select">
                    <option value="">{{ __('portal.all_departments') }}</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->slug }}" @selected(request('department') === $department->slug)>
                            {{ $department->t("name") }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('portal.type') }}</label>
                <select name="type" class="form-select">
                    <option value="">{{ __('portal.all_types') }}</option>
                    <option value="running" @selected(request('type') === 'running')>{{ __('portal.on_going') }}</option>
                    <option value="proposed" @selected(request('type') === 'proposed')>{{ __('portal.proposed') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('portal.district') }}</label>
                <select name="district" class="form-select">
                    <option value="">{{ __('portal.all_districts') }}</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected((string) request('district') === (string) $district->id)>
                            {{ $district->t("name") }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn famie-btn w-100">{{ __('portal.filter') }}</button>
            </div>
        </form>

        <div class="row">
            @forelse($schemes as $i => $scheme)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="alf-scheme-card card h-100 border-0 shadow-sm rounded-3 overflow-hidden d-flex flex-column">
                        <div class="alf-media-card bg-img position-relative"
                             data-alf-fit="bg"
                             data-alf-w="{{ \App\Support\ImageSlots::get('scheme_cover')['width'] }}"
                             data-alf-h="{{ \App\Support\ImageSlots::get('scheme_cover')['height'] }}"
                             style="background-image:url('{{ $scheme->imageUrl(($i % 6) + 4) }}');"></div>
                        <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        @php
                            $open = $scheme->isOpenForApplications();
                            $daysLeft = $scheme->last_date ? now()->startOfDay()->diffInDays($scheme->last_date->copy()->startOfDay(), false) : null;
                        @endphp
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                            <div class="d-flex gap-1 flex-wrap">
                                <span class="badge {{ $scheme->type === 'running' ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1">
                                    {{ $scheme->typeLabel() }}
                                </span>
                                <span class="badge bg-secondary px-2 py-1">{{ $scheme->scopeLabel() }}</span>
                            </div>
                            @if($scheme->type === 'running')
                                @if($open && $daysLeft !== null && $daysLeft >= 0)
                                    <span class="badge bg-primary px-2 py-1"><i class="fa-solid fa-clock me-1"></i>{{ __('portal.days_left', ['count' => (int) $daysLeft]) }}</span>
                                @elseif(! $open)
                                    <span class="badge bg-danger px-2 py-1">{{ __('portal.closed') }}</span>
                                @endif
                            @endif
                        </div>
                        <h5 class="card-title fw-bold mb-1 text-dark">{{ $scheme->t("title") }}</h5>
                        <p class="small text-muted mb-2"><i class="fa-solid fa-building me-1 text-secondary"></i>{{ $scheme->department?->t("name") }}</p>
                        @if($scheme->last_date)
                            <p class="small text-secondary mb-2">
                                <i class="fa-regular fa-calendar-days me-1 text-muted"></i>{{ __('portal.last_date') }}: <strong>{{ $scheme->last_date->format('d M Y') }}</strong>
                            </p>
                        @endif
                        <p class="small text-muted flex-grow-1 mb-3">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->t("description")), 110) }}</p>

                        <div class="alf-card-actions mt-auto pt-3 border-top d-flex gap-2 align-items-center">
                            <a href="{{ route('schemes.show', $scheme->slug) }}" class="alf-btn-details {{ $open || $scheme->type === 'proposed' ? '' : 'w-100' }}">
                                <i class="fa-solid fa-circle-info me-1"></i> {{ __('portal.view_details') }}
                            </a>
                            @if($open)
                                <a href="{{ route('schemes.apply', $scheme->slug) }}" class="alf-btn-apply alf-btn-apply-pulse">
                                    <span class="alf-pulse-beacon" aria-hidden="true">
                                        <span class="alf-beacon-ping"></span>
                                        <span class="alf-beacon-dot"></span>
                                    </span>
                                    <i class="fa-solid fa-paper-plane me-1"></i> {{ __('portal.apply_now') }}
                                </a>
                            @elseif($scheme->type === 'proposed')
                                <a href="{{ route('schemes.show', $scheme->slug) }}" class="alf-btn-interest">
                                    <i class="fa-solid fa-bell me-1"></i> {{ __('portal.express_interest') }}
                                </a>
                            @endif
                        </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">{{ __('portal.no_schemes_match') }}</div>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $schemes->links('pagination::bootstrap-5') }}
        </div>
    </div>
</section>
@endsection
