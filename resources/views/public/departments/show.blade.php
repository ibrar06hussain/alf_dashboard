@extends('layouts.public')

@section('title', $department->name)
@section('meta_description', $department->vision)

@section('content')
<section class="breadcrumb-area bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/2.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ $department->name }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ $department->name }}</li>
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
                    <p>Department Profile</p>
                    <h2><span>{{ $department->name }}</span></h2>
                </div>

                @if($department->vision)
                    <h5>Vision</h5>
                    <p class="mb-4">{{ $department->vision }}</p>
                @endif

                @if($department->mission)
                    <h5>Mission</h5>
                    <p class="mb-4">{{ $department->mission }}</p>
                @endif

                @if($department->objectives)
                    <h5>Objectives</h5>
                    <ul class="mb-4">
                        @foreach(preg_split("/\r\n|\n|\r/", $department->objectives) as $line)
                            @if(trim($line) !== '')
                                <li>{{ $line }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                @if($department->leadership_info)
                    <h5>Leadership</h5>
                    <p>{{ $department->leadership_info }}</p>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <div class="alf-dept-card">
                    <h4 class="h5 mb-3">Key Statistics</h4>
                    <div class="alf-metric-grid">
                        @forelse($department->stats as $stat)
                            <div class="alf-metric-chip">
                                <span class="val">{{ $stat->stat_value }}</span>
                                <span class="lbl">{{ $stat->stat_label }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Statistics will be published soon.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('schemes.index', ['department' => $department->slug]) }}" class="btn famie-btn">View Schemes</a>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12">
                <div class="section-heading">
                    <p>Opportunities</p>
                    <h2><span>Department</span> Schemes</h2>
                </div>
            </div>
            @forelse($department->schemes as $scheme)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="border rounded-3 p-4 h-100 bg-white shadow-sm">
                        <span class="badge {{ $scheme->type === 'running' ? 'bg-success' : 'bg-warning text-dark' }} mb-2">
                            {{ $scheme->typeLabel() }}
                        </span>
                        <h5>{{ $scheme->title }}</h5>
                        <p class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->description), 110) }}</p>
                        <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn btn-sm">View Details</a>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted">No active schemes for this department yet.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
