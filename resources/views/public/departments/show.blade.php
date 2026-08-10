@extends('layouts.public')

@section('title', $department->t("name"))
@section('meta_description', $department->t("vision"))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ $department->coverUrl() }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ $department->t("name") }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ $department->t("name") }}</li>
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
                <div class="section-heading mb-4">
                    <p>{{ __('portal.department_profile') }}</p>
                    <h2><span>{{ $department->t("name") }}</span></h2>
                </div>

                @if($department->t("vision"))
                    <h5>{{ __('portal.vision') }}</h5>
                    <p class="mb-4">{{ $department->t("vision") }}</p>
                @endif

                @if($department->t("mission"))
                    <h5>{{ __('portal.mission') }}</h5>
                    <p class="mb-4">{{ $department->t("mission") }}</p>
                @endif

                @if($department->t("objectives"))
                    <h5>{{ __('portal.objectives') }}</h5>
                    <ul class="mb-4">
                        @foreach(preg_split("/\r\n|\n|\r/", $department->t("objectives")) as $line)
                            @if(trim($line) !== '')
                                <li>{{ $line }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                @if($department->t("leadership_info"))
                    <h5>{{ __('portal.leadership') }}</h5>
                    <p>{{ $department->t("leadership_info") }}</p>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <div class="alf-dept-card">
                    <h4 class="h5 mb-3">{{ __('portal.key_statistics') }}</h4>
                    <div class="alf-metric-grid">
                        @forelse($department->stats as $stat)
                            <div class="alf-metric-chip">
                                <span class="val">{{ $stat->stat_value }}</span>
                                <span class="lbl">{{ $stat->t("stat_label") }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('portal.stats_soon') }}</p>
                        @endforelse
                    </div>
                    <a href="{{ route('schemes.index', ['department' => $department->slug]) }}" class="btn famie-btn">{{ __('portal.view_schemes') }}</a>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12">
                <div class="section-heading">
                    <p>{{ __('portal.opportunities') }}</p>
                    <h2><span>{{ __('portal.department') }}</span> {{ __('portal.schemes') }}</h2>
                </div>
            </div>
            @forelse($department->schemes as $scheme)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="border rounded-3 p-4 h-100 bg-white shadow-sm">
                        <span class="badge {{ $scheme->type === 'running' ? 'bg-success' : 'bg-warning text-dark' }} mb-2">
                            {{ $scheme->typeLabel() }}
                        </span>
                        <h5>{{ $scheme->t("title") }}</h5>
                        <p class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->t("description")), 110) }}</p>
                        <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn btn-sm">{{ __('portal.view_details') }}</a>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted">{{ __('portal.no_dept_schemes') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
