<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Government of Gilgit-Baltistan — Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries. Apply online for ALF schemes.')">
    <title>@yield('title', 'ALF Gilgit-Baltistan') | ALF Portal</title>
    <link rel="icon" href="{{ asset('frontend/img/core-img/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('frontend/style.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/alf-portal.css') }}">
    <style>.preloader{transition:opacity .4s}.preloader.is-hidden{opacity:0;visibility:hidden}</style>
    @stack('styles')
</head>
<body>
@php
    $navDepartments = \App\Models\Department::query()
        ->where('is_active', true)
        ->orderBy('display_order')
        ->get();
@endphp

<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="preloader d-flex align-items-center justify-content-center">
    <div class="spinner"></div>
</div>

<header class="header-area">
    <div class="top-header-area">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="top-header-content d-flex align-items-center justify-content-between">
                        <div class="top-header-meta">
                            <p>Welcome to <span>ALF Gilgit-Baltistan</span> — Secretariat of Agriculture, Livestock &amp; Dairy Development, and Fisheries</p>
                        </div>
                        <div class="top-header-meta text-end">
                            <a href="mailto:info@alf.gb.gov.pk" data-bs-toggle="tooltip" data-bs-placement="bottom" title="info@alf.gb.gov.pk">
                                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                <span>Email: info@alf.gb.gov.pk</span>
                            </a>
                            <a href="{{ url('/admin/login') }}">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <span>Admin Login</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="famie-main-menu">
        <div class="classy-nav-container breakpoint-off">
            <div class="container">
                <nav class="classy-navbar justify-content-between" id="famieNav" aria-label="Main navigation">
                    <a href="{{ route('home') }}" class="nav-brand alf-text-logo" aria-label="ALF Gilgit Baltistan">
                        <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
                    </a>

                    <div class="classy-navbar-toggler">
                        <span class="navbarToggler"><span></span><span></span><span></span></span>
                    </div>

                    <div class="classy-menu">
                        <div class="classycloseIcon">
                            <div class="cross-wrap"><span class="top"></span><span class="bottom"></span></div>
                        </div>
                        <div class="classynav">
                            <ul>
                                <li class="{{ request()->routeIs('home') ? 'active' : '' }}">
                                    <a href="{{ route('home') }}">Home</a>
                                </li>
                                <li>
                                    <a href="#">Departments</a>
                                    <ul class="dropdown">
                                        @foreach($navDepartments as $dept)
                                            <li>
                                                <a href="{{ route('departments.show', $dept->slug) }}">{{ $dept->name }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                                <li class="{{ request()->routeIs('schemes.*') ? 'active' : '' }}">
                                    <a href="{{ route('schemes.index') }}">Schemes</a>
                                    <ul class="dropdown">
                                        <li><a href="{{ route('schemes.index', ['type' => 'running']) }}">On Going Schemes</a></li>
                                        <li><a href="{{ route('schemes.index', ['type' => 'proposed']) }}">Proposed for CM</a></li>
                                        <li><a href="{{ url('/#cm-proposed-schemes') }}">CM Proposals (Home)</a></li>
                                    </ul>
                                </li>
                                <li class="{{ request()->routeIs('applications.track') ? 'active' : '' }}">
                                    <a href="{{ route('applications.track') }}">Track Application</a>
                                </li>
                                <li class="{{ request()->routeIs('about') ? 'active' : '' }}">
                                    <a href="{{ route('about') }}">About</a>
                                </li>
                                <li class="{{ request()->routeIs('contact') ? 'active' : '' }}">
                                    <a href="{{ route('contact') }}">Contact</a>
                                </li>
                                <li>
                                    <a href="{{ url('/admin/login') }}">Admin Login</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</header>

<main id="main-content">
    @if(session('success'))
        <div class="container mt-4">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="container mt-4">
            <div class="alert alert-danger">{{ session('error') }}</div>
        </div>
    @endif

    @yield('content')
</main>

<footer class="footer-area" role="contentinfo">
    <div class="main-footer bg-img bg-overlay section-padding-80-0" style="background-image: url('{{ asset('frontend/img/bg-img/3.jpg') }}');">
        <div class="container">
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <a href="{{ route('home') }}" class="foo-logo alf-text-logo alf-text-logo--footer d-inline-block mb-30" aria-label="ALF Gilgit Baltistan">
                            <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
                        </a>
                        <p>
                            Secretariat of Agriculture, Livestock &amp; Dairy Development, and Fisheries —
                            Government of Gilgit-Baltistan.
                        </p>
                        <div class="contact-info">
                            <p><i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Secretariat Complex, Gilgit</span></p>
                            <p><i class="fa-solid fa-envelope" aria-hidden="true"></i><span>info@alf.gb.gov.pk</span></p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">Departments</h5>
                        <nav class="footer-widget-nav">
                            <ul>
                                @foreach($navDepartments as $dept)
                                    <li><a href="{{ route('departments.show', $dept->slug) }}">{{ $dept->name }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">Quick Links</h5>
                        <nav class="footer-widget-nav">
                            <ul>
                                <li><a href="{{ route('schemes.index') }}">All Schemes</a></li>
                                <li><a href="{{ route('schemes.index', ['type' => 'running']) }}">On Going Schemes</a></li>
                                <li><a href="{{ route('schemes.index', ['type' => 'proposed']) }}">Proposed Schemes</a></li>
                                <li><a href="{{ route('applications.track') }}">Track Application</a></li>
                                <li><a href="{{ route('about') }}">About</a></li>
                                <li><a href="{{ route('contact') }}">Contact</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">Citizen Services</h5>
                        <p class="text-white-50 mb-3">Apply online with CNIC verification and track your application status in real time.</p>
                        <a href="{{ route('schemes.index') }}" class="btn famie-btn">Browse Schemes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="copywrite-area">
        <div class="container">
            <div class="copywrite-text">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p>
                            Copyright &copy; <span class="current-year">{{ date('Y') }}</span>
                            Government of Gilgit-Baltistan — ALF Secretariat. All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="footer-nav">
                            <nav>
                                <ul>
                                    <li><a href="{{ route('about') }}">About</a></li>
                                    <li><a href="{{ route('schemes.index') }}">Schemes</a></li>
                                    <li><a href="{{ route('applications.track') }}">Track</a></li>
                                    <li><a href="{{ route('contact') }}">Contact</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="{{ asset('frontend/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('frontend/js/swiper-bundle.min.js') }}"></script>
<script src="{{ asset('frontend/js/glightbox.min.js') }}"></script>
<script src="{{ asset('frontend/js/aos.js') }}"></script>
<script src="{{ asset('frontend/js/jarallax.min.js') }}"></script>
<script src="{{ asset('frontend/js/jarallax-video.min.js') }}"></script>
<script src="{{ asset('frontend/js/main.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var preloader = document.querySelector('.preloader');
    if (preloader) {
        preloader.classList.add('is-hidden');
        setTimeout(function () { preloader.remove(); }, 500);
    }
});
</script>
@stack('scripts')
</body>
</html>
