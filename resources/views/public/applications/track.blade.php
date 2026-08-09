@extends('layouts.public')

@section('title', 'Track Application')

@section('content')
<section class="breadcrumb-area bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/10.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">Track Application</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">Track</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="border rounded-3 p-4 bg-white shadow-sm mb-4">
                    <h4 class="mb-3">Find your application</h4>
                    <p class="text-muted">Enter your application number and CNIC to view current status.</p>

                    <form method="post" action="{{ route('applications.track') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Application Number</label>
                            <input type="text" name="application_no" class="form-control @error('application_no') is-invalid @enderror"
                                   value="{{ old('application_no', request('application_no')) }}" placeholder="ALF-YYYYMMDD-XXXXXX" required>
                            @error('application_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNIC (13 digits)</label>
                            <input type="text" name="cnic" class="form-control js-cnic @error('cnic') is-invalid @enderror"
                                   value="{{ old('cnic', request('cnic')) }}" maxlength="15" inputmode="numeric" required>
                            @error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn famie-btn">Track Status</button>
                    </form>
                </div>

                @if(!empty($notFound))
                    <div class="alert alert-warning">No application found for the provided application number and CNIC.</div>
                @endif

                @if($application)
                    <div class="border rounded-3 p-4 bg-white shadow-sm">
                        <h4 class="mb-3">Application Status</h4>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Application No.</dt>
                            <dd class="col-sm-8"><strong>{{ $application->application_no }}</strong></dd>

                            <dt class="col-sm-4">Applicant</dt>
                            <dd class="col-sm-8">{{ $application->applicant?->full_name }}</dd>

                            <dt class="col-sm-4">Scheme</dt>
                            <dd class="col-sm-8">{{ $application->scheme?->title }}</dd>

                            <dt class="col-sm-4">Department</dt>
                            <dd class="col-sm-8">{{ $application->scheme?->department?->name }}</dd>

                            <dt class="col-sm-4">District</dt>
                            <dd class="col-sm-8">{{ $application->district?->name }}</dd>

                            <dt class="col-sm-4">Submitted</dt>
                            <dd class="col-sm-8">{{ optional($application->submitted_at)->format('d M Y, h:i A') }}</dd>

                            <dt class="col-sm-4">Current Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-primary">
                                    {{ \App\Models\Application::STATUSES[$application->status] ?? ucfirst($application->status) }}
                                </span>
                            </dd>

                            @if($application->status === 'rejected' && $application->rejection_reason)
                                <dt class="col-sm-4">Rejection Reason</dt>
                                <dd class="col-sm-8">{{ $application->rejection_reason }}</dd>
                            @endif
                        </dl>

                        @if($application->statusLogs->isNotEmpty())
                            <hr>
                            <h5 class="mb-3">Status Timeline</h5>
                            <ul class="list-group list-group-flush">
                                @foreach($application->statusLogs as $log)
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <strong>{{ \App\Models\Application::STATUSES[$log->to_status] ?? $log->to_status }}</strong>
                                            <span class="small text-muted">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                        </div>
                                        @if($log->note)
                                            <div class="small text-muted">{{ $log->note }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-cnic').forEach(function (input) {
    input.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 13);
    });
});
</script>
@endpush
