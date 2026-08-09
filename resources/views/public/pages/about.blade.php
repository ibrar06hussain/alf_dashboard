@extends('layouts.public')

@section('title', 'About ALF Gilgit-Baltistan')

@section('content')
<section class="breadcrumb-area bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/5.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">About Us</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">About</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="{{ asset('frontend/img/bg-img/2.jpg') }}" class="img-fluid rounded-3" alt="ALF Gilgit-Baltistan">
            </div>
            <div class="col-lg-6">
                <div class="section-heading">
                    <p>Government of Gilgit-Baltistan</p>
                    <h2><span>ALF</span> Gilgit-Baltistan</h2>
                </div>
                <p>
                    The Secretariat of Agriculture, Livestock &amp; Dairy Development, and Fisheries (ALF)
                    provides a single-window digital portal for citizens of Gilgit-Baltistan to discover schemes,
                    submit applications with CNIC-linked identity, and track progress transparently.
                </p>
                <p>
                    Our mission is to digitize farmer and livestock services, strengthen district-level delivery,
                    and support sustainable food systems across the region’s unique highland economy.
                </p>
            </div>
        </div>

        <div class="row">
            @foreach($departments as $department)
                <div class="col-md-4 mb-4">
                    <div class="border rounded-3 p-4 h-100">
                        <h5>{{ $department->name }}</h5>
                        <p class="small text-muted">{{ \Illuminate\Support\Str::limit($department->mission, 160) }}</p>
                        <a href="{{ route('departments.show', $department->slug) }}">Learn more →</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
