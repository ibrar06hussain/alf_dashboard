@extends('layouts.public')

@section('title', 'Application Submitted')

@section('content')
<section class="section-padding-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="border rounded-3 p-5 bg-white shadow-sm text-center">
                    <div class="mb-3 text-success">
                        <i class="fa-solid fa-circle-check fa-3x"></i>
                    </div>
                    <h2 class="mb-3">Application Submitted</h2>
                    <p class="text-muted">Your application has been received successfully. Please save your application number for tracking.</p>

                    <div class="alert alert-success">
                        <div class="small text-uppercase mb-1">Application Number</div>
                        <div class="fs-4 fw-bold">{{ $application->application_no }}</div>
                    </div>

                    <dl class="row text-start mt-4 mb-4">
                        <dt class="col-sm-4">Applicant</dt>
                        <dd class="col-sm-8">{{ $application->applicant?->full_name }}</dd>
                        <dt class="col-sm-4">Scheme</dt>
                        <dd class="col-sm-8">{{ $application->scheme?->title }}</dd>
                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8">{{ $application->scheme?->department?->name }}</dd>
                        <dt class="col-sm-4">District</dt>
                        <dd class="col-sm-8">{{ $application->district?->name }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">{{ \App\Models\Application::STATUSES[$application->status] ?? $application->status }}</dd>
                    </dl>

                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="{{ route('applications.track', ['application_no' => $application->application_no]) }}" class="btn famie-btn">Track Application</a>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
