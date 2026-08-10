<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', \App\Models\SiteSetting::text('footer_about'))">
    <title>@yield('title', \App\Models\SiteSetting::text('site_name')) | ALF Portal</title>
    <link rel="icon" href="{{ \App\Support\Media::site('favicon', 'frontend/img/core-img/favicon.ico') }}">
    @if(app()->getLocale() === 'ur')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    @endif
    <link rel="stylesheet" href="{{ asset('frontend/style.css') }}?v={{ @filemtime(public_path('frontend/style.css')) ?: '1' }}">
    {{-- Hard bump + filemtime: Hostinger CDN caches CSS for 7 days without a changing query string --}}
    <link rel="stylesheet" href="{{ asset('frontend/css/alf-portal.css') }}?v=20260810-lang-{{ @filemtime(public_path('frontend/css/alf-portal.css')) ?: '1' }}">
    <style>
        /* Critical top-bar + language switcher (works even if alf-portal.css fails to load) */
        .preloader{transition:opacity .4s}.preloader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
        .top-header-area{display:block!important;visibility:visible!important;background:#f2f4f5!important}
        .top-header-content{height:auto!important;min-height:44px;padding:8px 0!important;overflow:visible!important;display:flex!important;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
        .top-header-content .alf-top-welcome,
        .top-header-content .alf-top-actions{flex:1 1 auto!important;max-width:none!important;width:auto!important}
        .top-header-content .alf-top-actions{flex:0 1 auto!important;display:flex!important;align-items:center;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .alf-lang-switch{display:inline-flex!important;align-items:center;border:1px solid #cfd5d8;border-radius:999px;overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,17,18,.06)}
        .alf-lang-switch .alf-lang-btn{display:inline-block!important;padding:6px 14px!important;font-size:12px!important;font-weight:700!important;color:#334155!important;text-decoration:none!important;line-height:1.4;background:transparent;white-space:nowrap;margin:0!important}
        .alf-lang-switch .alf-lang-btn.is-active{background:#77b122!important;color:#fff!important}
        .alf-lang-switch .alf-lang-btn:hover{color:#77b122!important;background:#f4f8ec}
        .alf-lang-switch .alf-lang-btn.is-active:hover{color:#fff!important;background:#77b122!important}
        .top-header-content .top-header-meta .alf-lang-switch a{margin-right:0!important;color:#334155}
        .top-header-content .top-header-meta .alf-lang-switch a.is-active{color:#fff!important}
        .alf-nav-lang-inline{display:inline-flex;align-items:center;margin-left:12px}
        .famie-main-menu .alf-lang-switch{border-color:#d7e3c2}
        @media (max-width:767px){
            .top-header-content .alf-top-welcome{display:none!important}
            .top-header-content .alf-top-actions{width:100%!important;justify-content:space-between!important}
        }
    </style>
    @stack('styles')
</head>
<body class="{{ app()->getLocale() === 'ur' ? 'locale-ur' : 'locale-en' }}">
@php
    $navDepartments = \App\Models\Department::query()
        ->where('is_active', true)
        ->orderBy('display_order')
        ->get();
    $currentLocale = app()->getLocale();
    $siteEmail = \App\Models\SiteSetting::text('contact_email');
    $sitePhone = \App\Models\SiteSetting::text('contact_phone');
    $logoPrimary = \App\Models\SiteSetting::text('logo_primary');
    $logoSecondary = \App\Models\SiteSetting::text('logo_secondary');
@endphp

<a class="skip-link" href="#main-content">{{ __('portal.skip_to_content') }}</a>

<div class="preloader d-flex align-items-center justify-content-center" id="alfPreloader">
    <div class="spinner"></div>
</div>

<header class="header-area">
    <div class="top-header-area">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="top-header-content d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="top-header-meta alf-top-welcome">
                            <p>{{ \App\Models\SiteSetting::text('welcome_message') }}</p>
                        </div>
                        <div class="top-header-meta alf-top-actions d-flex align-items-center flex-wrap gap-2 justify-content-end">
                            <div class="alf-lang-switch" role="group" aria-label="{{ __('portal.language') }}">
                                <a href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => url()->full()]) }}"
                                   class="alf-lang-btn {{ $currentLocale === 'en' ? 'is-active' : '' }}"
                                   hreflang="en"
                                   lang="en">EN</a>
                                <a href="{{ route('locale.switch', ['locale' => 'ur', 'redirect' => url()->full()]) }}"
                                   class="alf-lang-btn {{ $currentLocale === 'ur' ? 'is-active' : '' }}"
                                   hreflang="ur"
                                   lang="ur">اردو</a>
                            </div>
                            @if($siteEmail)
                                <a href="mailto:{{ $siteEmail }}" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ $siteEmail }}">
                                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                    <span>{{ __('portal.email') }}</span>
                                </a>
                            @endif
                            @if($sitePhone)
                                <a href="tel:{{ preg_replace('/\s+/', '', $sitePhone) }}">
                                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                    <span>{{ $sitePhone }}</span>
                                </a>
                            @endif
                            <a href="{{ url('/admin/login') }}">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <span>{{ __('portal.admin_login') }}</span>
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
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <a href="{{ route('home') }}" class="nav-brand alf-text-logo"
                           lang="{{ $currentLocale }}"
                           dir="{{ $currentLocale === 'ur' ? 'rtl' : 'ltr' }}"
                           aria-label="{{ \App\Models\SiteSetting::text('site_name') }}">
                            <span class="alf-logo-primary">{{ $logoPrimary }}</span><span class="alf-logo-secondary">{{ $logoSecondary }}</span>
                        </a>
                        <div class="alf-nav-lang-inline alf-lang-switch" role="group" aria-label="{{ __('portal.language') }}">
                            <a href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => url()->full()]) }}"
                               class="alf-lang-btn {{ $currentLocale === 'en' ? 'is-active' : '' }}"
                               hreflang="en"
                               lang="en">EN</a>
                            <a href="{{ route('locale.switch', ['locale' => 'ur', 'redirect' => url()->full()]) }}"
                               class="alf-lang-btn {{ $currentLocale === 'ur' ? 'is-active' : '' }}"
                               hreflang="ur"
                               lang="ur">اردو</a>
                        </div>
                    </div>

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
                                    <a href="{{ route('home') }}">{{ __('portal.home') }}</a>
                                </li>
                                <li>
                                    <a href="#">{{ __('portal.departments') }}</a>
                                    <ul class="dropdown">
                                        @foreach($navDepartments as $dept)
                                            <li>
                                                <a href="{{ route('departments.show', $dept->slug) }}">{{ $dept->t("name") }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                                <li class="{{ request()->routeIs('schemes.*') ? 'active' : '' }}">
                                    <a href="{{ route('schemes.index') }}">{{ __('portal.schemes') }}</a>
                                    <ul class="dropdown">
                                        <li><a href="{{ route('schemes.index', ['type' => 'running']) }}">{{ __('portal.on_going_schemes') }}</a></li>
                                        <li><a href="{{ route('schemes.index', ['type' => 'proposed']) }}">{{ __('portal.proposed_for_cm') }}</a></li>
                                        <li><a href="{{ url('/#cm-proposed-schemes') }}">{{ __('portal.cm_proposals_home') }}</a></li>
                                    </ul>
                                </li>
                                <li class="{{ request()->routeIs('applications.track') ? 'active' : '' }}">
                                    <a href="{{ route('applications.track') }}">{{ __('portal.track_application') }}</a>
                                </li>
                                <li class="{{ request()->routeIs('about') ? 'active' : '' }}">
                                    <a href="{{ route('about') }}">{{ __('portal.about') }}</a>
                                </li>
                                <li class="{{ request()->routeIs('contact') ? 'active' : '' }}">
                                    <a href="{{ route('contact') }}">{{ __('portal.contact') }}</a>
                                </li>
                                <li class="alf-nav-lang">
                                    <a href="#">{{ __('portal.language') }}</a>
                                    <ul class="dropdown">
                                        <li>
                                            <a href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => url()->full()]) }}"
                                               hreflang="en"
                                               lang="en"
                                               class="{{ $currentLocale === 'en' ? 'alf-lang-active' : '' }}">{{ __('portal.english') }}</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('locale.switch', ['locale' => 'ur', 'redirect' => url()->full()]) }}"
                                               hreflang="ur"
                                               lang="ur"
                                               class="{{ $currentLocale === 'ur' ? 'alf-lang-active' : '' }}">{{ __('portal.urdu') }}</a>
                                        </li>
                                    </ul>
                                </li>
                                <li>
                                    <a href="{{ url('/admin/login') }}">{{ __('portal.admin_login') }}</a>
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
    <div class="main-footer alf-footer-bg bg-img bg-overlay section-padding-80-0"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('footer')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('footer')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('footer_background', 'frontend/img/bg-img/3.jpg') }}');">
        <div class="container">
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <a href="{{ route('home') }}" class="foo-logo alf-text-logo alf-text-logo--footer d-inline-block mb-30"
                           lang="{{ $currentLocale }}"
                           dir="{{ $currentLocale === 'ur' ? 'rtl' : 'ltr' }}"
                           aria-label="{{ \App\Models\SiteSetting::text('site_name') }}">
                            <span class="alf-logo-primary">{{ $logoPrimary }}</span><span class="alf-logo-secondary">{{ $logoSecondary }}</span>
                        </a>
                        <p>{{ \App\Models\SiteSetting::text('footer_about') }}</p>
                        <div class="contact-info">
                            <p><i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>{{ \App\Models\SiteSetting::text('contact_address_short') }}</span></p>
                            @if($siteEmail)
                                <p><i class="fa-solid fa-envelope" aria-hidden="true"></i><span>{{ $siteEmail }}</span></p>
                            @endif
                            @if($sitePhone)
                                <p><i class="fa-solid fa-phone" aria-hidden="true"></i><span>{{ $sitePhone }}</span></p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">{{ __('portal.departments') }}</h5>
                        <nav class="footer-widget-nav">
                            <ul>
                                @foreach($navDepartments as $dept)
                                    <li><a href="{{ route('departments.show', $dept->slug) }}">{{ $dept->t("name") }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">{{ __('portal.quick_links') }}</h5>
                        <nav class="footer-widget-nav">
                            <ul>
                                <li><a href="{{ route('schemes.index') }}">{{ __('portal.all_schemes') }}</a></li>
                                <li><a href="{{ route('schemes.index', ['type' => 'running']) }}">{{ __('portal.on_going_schemes') }}</a></li>
                                <li><a href="{{ route('schemes.index', ['type' => 'proposed']) }}">{{ __('portal.proposed_schemes') }}</a></li>
                                <li><a href="{{ route('applications.track') }}">{{ __('portal.track_application') }}</a></li>
                                <li><a href="{{ route('about') }}">{{ __('portal.about') }}</a></li>
                                <li><a href="{{ route('contact') }}">{{ __('portal.contact') }}</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-widget mb-80">
                        <h5 class="widget-title">{{ __('portal.citizen_services') }}</h5>
                        <p class="text-white-50 mb-3">{{ \App\Models\SiteSetting::text('citizen_services_blurb') }}</p>
                        <a href="{{ route('schemes.index') }}" class="btn famie-btn">{{ __('portal.browse_schemes') }}</a>
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
                        <p>{!! __('portal.powered_by', ['year' => date('Y')]) !!}</p>
                    </div>
                    <div class="col-md-6">
                        <div class="footer-nav">
                            <nav>
                                <ul>
                                    <li><a href="{{ route('about') }}">{{ __('portal.about') }}</a></li>
                                    <li><a href="{{ route('schemes.index') }}">{{ __('portal.schemes') }}</a></li>
                                    <li><a href="{{ route('applications.track') }}">{{ __('portal.track') }}</a></li>
                                    <li><a href="{{ route('contact') }}">{{ __('portal.contact') }}</a></li>
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
<script src="{{ asset('frontend/js/alf-media-fit.js') }}"></script>
<script>
(function () {
    function hidePreloader() {
        var preloader = document.getElementById('alfPreloader') || document.querySelector('.preloader');
        if (!preloader) return;
        preloader.classList.add('is-hidden');
        setTimeout(function () {
            if (preloader && preloader.parentNode) preloader.parentNode.removeChild(preloader);
        }, 400);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hidePreloader);
    } else {
        hidePreloader();
    }
    // Failsafe if other scripts hang
    setTimeout(hidePreloader, 2500);
})();
</script>
@stack('scripts')
</body>
</html>
