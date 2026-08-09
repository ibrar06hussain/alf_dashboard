@extends('layouts.public')

@section('title', $scheme->title)

@section('content')
<section class="breadcrumb-area bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/6.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ $scheme->title }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('schemes.index') }}" class="text-white">Schemes</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">Details</li>
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
                    <span class="badge bg-secondary">{{ ucfirst($scheme->scope) }} Scope</span>
                    <span class="badge bg-info text-dark">{{ $scheme->department?->name }}</span>
                </div>

                <h3 class="mb-3">{{ $scheme->title }}</h3>

                @if($scheme->description)
                    <h5>Description</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->description)) !!}</div>
                @endif

                @if($scheme->eligibility_criteria)
                    <h5>Eligibility Criteria</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->eligibility_criteria)) !!}</div>
                @endif

                @if($scheme->terms_conditions)
                    <h5>Terms &amp; Conditions</h5>
                    <div class="mb-4">{!! nl2br(e($scheme->terms_conditions)) !!}</div>
                @endif

                @if($scheme->districts->isNotEmpty())
                    <h5>Covered Districts</h5>
                    <ul class="mb-4">
                        @foreach($scheme->districts as $district)
                            <li>{{ $district->name }} @if($district->pivot->quota) — Quota: {{ $district->pivot->quota }} @endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <div class="border rounded-3 p-4 bg-light mb-4">
                    <h5 class="mb-3">Scheme Info</h5>
                    <p class="mb-2"><strong>Status:</strong> {{ ucfirst($scheme->status) }}</p>
                    @if($scheme->budget)
                        <p class="mb-2"><strong>Budget:</strong> PKR {{ number_format($scheme->budget, 0) }}</p>
                    @endif
                    @if($scheme->start_date)
                        <p class="mb-2"><strong>Start date:</strong> {{ $scheme->start_date->format('d M Y') }}</p>
                    @endif
                    @if($scheme->last_date)
                        @php $daysLeft = now()->startOfDay()->diffInDays($scheme->last_date->copy()->startOfDay(), false); @endphp
                        <p class="mb-2"><strong>Last date:</strong> {{ $scheme->last_date->format('d M Y') }}</p>
                        @if($scheme->isOpenForApplications() && $daysLeft >= 0)
                            <p class="mb-2"><strong>Time left:</strong> {{ (int) $daysLeft }} day(s)</p>
                        @endif
                    @endif

                    @if($scheme->isOpenForApplications())
                        <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn famie-btn w-100 mt-3">Apply Now</a>
                    @elseif($scheme->type === 'proposed')
                        <p class="small text-muted mt-3 mb-0">This is a proposed / upcoming scheme. Express your interest below to be notified.</p>
                    @else
                        <div class="alert alert-warning mt-3 mb-0">Applications are currently closed for this scheme.</div>
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
