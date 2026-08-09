@extends('layouts.public')

@section('title', 'Schemes')

@section('content')
<section class="breadcrumb-area bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/8.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">Schemes</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">Schemes</li>
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
                <label class="form-label">Department</label>
                <select name="department" class="form-select">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->slug }}" @selected(request('department') === $department->slug)>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All types</option>
                    <option value="running" @selected(request('type') === 'running')>On Going</option>
                    <option value="proposed" @selected(request('type') === 'proposed')>Proposed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">District</label>
                <select name="district" class="form-select">
                    <option value="">All districts</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected((string) request('district') === (string) $district->id)>
                            {{ $district->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn famie-btn w-100">Filter</button>
            </div>
        </form>

        <div class="row">
            @forelse($schemes as $scheme)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="border rounded-3 p-4 h-100 bg-white shadow-sm">
                        @php
                            $open = $scheme->isOpenForApplications();
                            $daysLeft = $scheme->last_date ? now()->startOfDay()->diffInDays($scheme->last_date->copy()->startOfDay(), false) : null;
                        @endphp
                        <div class="d-flex justify-content-between mb-2 flex-wrap gap-1">
                            <span class="badge {{ $scheme->type === 'running' ? 'bg-success' : 'bg-warning text-dark' }}">
                                {{ $scheme->typeLabel() }}
                            </span>
                            <span class="badge bg-secondary">{{ ucfirst($scheme->scope) }}</span>
                            @if($scheme->type === 'running')
                                @if($open && $daysLeft !== null && $daysLeft >= 0)
                                    <span class="badge bg-primary">{{ (int) $daysLeft }} day(s) left</span>
                                @elseif(! $open)
                                    <span class="badge bg-danger">Closed</span>
                                @endif
                            @endif
                        </div>
                        <h5>{{ $scheme->title }}</h5>
                        <p class="small text-muted mb-1">{{ $scheme->department?->name }}</p>
                        @if($scheme->last_date)
                            <p class="small mb-2">Last date: <strong>{{ $scheme->last_date->format('d M Y') }}</strong></p>
                        @endif
                        <p class="small">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->description), 110) }}</p>
                        <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn btn-sm">View Details</a>
                        @if($open)
                            <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn btn-outline-success btn-sm">Apply</a>
                        @elseif($scheme->type === 'proposed')
                            <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn btn-outline-warning btn-sm">Express Interest</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">No schemes match your filters.</div>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $schemes->links('pagination::bootstrap-5') }}
        </div>
    </div>
</section>
@endsection
