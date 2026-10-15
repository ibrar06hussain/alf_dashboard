@extends('layouts.public')

@section('title', __('portal.application_submitted'))

@section('content')
<section class="section-padding-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="border rounded-3 p-5 bg-white shadow-sm text-center">
                    <div class="mb-3 text-success">
                        <i class="fa-solid fa-circle-check fa-3x"></i>
                    </div>
                    <h2 class="mb-3">{{ __('portal.application_submitted') }}</h2>
                    <p class="text-muted">{{ __('portal.application_submitted_blurb') }}</p>

                    <div class="alert alert-success">
                        <div class="small text-uppercase mb-1">{{ __('portal.application_no') }}</div>
                        <div class="fs-4 fw-bold">{{ $application->application_no }}</div>
                    </div>

                    <dl class="row text-start mt-4 mb-4">
                        <dt class="col-sm-4">{{ __('portal.applicant') }}</dt>
                        <dd class="col-sm-8">{{ $application->applicant?->full_name }}</dd>
                        <dt class="col-sm-4">{{ __('portal.scheme') }}</dt>
                        <dd class="col-sm-8">{{ $application->scheme?->t("title") }}</dd>
                        <dt class="col-sm-4">{{ __('portal.department') }}</dt>
                        <dd class="col-sm-8">{{ $application->scheme?->department?->t("name") }}</dd>
                        <dt class="col-sm-4">{{ __('portal.district') }}</dt>
                        <dd class="col-sm-8">{{ $application->district?->t("name") }}</dd>
                        @if($application->applicant?->tehsil)
                            <dt class="col-sm-4">{{ __('portal.tehsil') }}</dt>
                            <dd class="col-sm-8">{{ $application->applicant->tehsil }}</dd>
                        @endif
                        @if($application->applicant?->union_council)
                            <dt class="col-sm-4">{{ __('portal.union_council') }}</dt>
                            <dd class="col-sm-8">{{ $application->applicant->union_council }}</dd>
                        @endif
                        @if($application->applicant?->village)
                            <dt class="col-sm-4">{{ __('portal.village') }}</dt>
                            <dd class="col-sm-8">{{ $application->applicant->village }}</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('portal.status') }}</dt>
                        <dd class="col-sm-8">{{ $application->statusLabel() }}</dd>
                    </dl>

                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="{{ route('applications.track', ['application_no' => $application->application_no]) }}" class="btn famie-btn">{{ __('portal.track_application') }}</a>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary">{{ __('portal.back_to_home') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
