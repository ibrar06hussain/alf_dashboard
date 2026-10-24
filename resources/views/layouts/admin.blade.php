<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — GB ALF Dashboard</title>
    <style>
        body .app-container { opacity: 1 !important; visibility: visible !important; }
    </style>
    <link href="{{ asset('admin-assets/assets/styles/vendors.46b0e993b2652496c5e9.css') }}" rel="stylesheet">
    <link href="{{ asset('admin-assets/assets/styles/main.46b0e993b2652496c5e9.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{{ asset('admin-assets/css/alf-admin.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php
    $user = auth()->user();
    $isExecutive = $user?->hasAnyRole(['Super Admin', 'Secretary']);
    $canManageSchemes = $user?->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries']);
    $canViewCm = $canManageSchemes;
@endphp
<div class="app-container app-theme-white body-tabs-shadow fixed-header fixed-sidebar">
    <div class="app-header header-shadow">
        <div class="app-header__logo">
            <a href="{{ route('admin.dashboard') }}" class="alf-text-logo" aria-label="ALF Gilgit Baltistan">
                <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
            </a>
            <div class="header__pane ms-auto">
                <div>
                    <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar">
                        <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                    </button>
                </div>
            </div>
        </div>
        <div class="app-header__mobile-menu">
            <div>
                <button type="button" class="hamburger hamburger--elastic mobile-toggle-nav">
                    <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                </button>
            </div>
        </div>
        <div class="app-header__menu">
            <span>
                <button type="button" class="btn-icon btn-icon-only btn btn-primary btn-sm mobile-toggle-header-nav">
                    <span class="btn-icon-wrapper"><i class="fa-solid fa-ellipsis-vertical"></i></span>
                </button>
            </span>
        </div>
        <div class="app-header__content">
            <div class="app-header-left">
                <div class="header-pane">
                    <strong>GB ALF Dashboard</strong>
                    <span class="text-muted ms-2 d-none d-md-inline">Agriculture · Livestock · Fisheries</span>
                </div>
            </div>
            <div class="app-header-right">
                <div class="header-btn-lg pe-0">
                    <div class="widget-content p-0">
                        <div class="widget-content-wrapper">
                            <div class="widget-content-left ms-3 header-user-info">
                                <div class="widget-heading">{{ $user?->name }}</div>
                                <div class="widget-subheading">{{ $user?->getRoleNames()->first() }}</div>
                            </div>
                            <div class="widget-content-right header-btn-lg">
                                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-main">
        <div class="app-sidebar sidebar-shadow">
            <div class="app-header__logo">
                <a href="{{ route('admin.dashboard') }}" class="alf-text-logo" aria-label="ALF Gilgit Baltistan">
                    <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
                </a>
                <div class="header__pane ms-auto">
                    <div>
                        <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar">
                            <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="scrollbar-sidebar">
                <div class="app-sidebar__inner">
                    <ul class="vertical-nav-menu">
                        <li class="app-sidebar__heading">Main</li>
                        <li class="{{ request()->routeIs('admin.dashboard') ? 'mm-active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}">
                                <i class="metismenu-icon fa-solid fa-rocket"></i> Dashboard
                            </a>
                        </li>
                        @if($isExecutive)
                            <li class="{{ request()->routeIs('admin.departments.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.departments.index') }}">
                                    <i class="metismenu-icon fa-solid fa-landmark"></i> Departments
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.districts.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.districts.index') }}">
                                    <i class="metismenu-icon fa-solid fa-location-dot"></i> Districts
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.carousel.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.carousel.index') }}">
                                    <i class="metismenu-icon fa-solid fa-images"></i> Carousel
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.media.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.media.edit') }}">
                                    <i class="metismenu-icon fa-solid fa-photo-film"></i> Site Media
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.site-info.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.site-info.edit') }}">
                                    <i class="metismenu-icon fa-solid fa-address-card"></i> Site Information
                                </a>
                            </li>
                        @endif
                        @if($canManageSchemes)
                            <li class="{{ request()->routeIs('admin.schemes.*') && request('type') !== 'proposed' ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.schemes.index') }}">
                                    <i class="metismenu-icon fa-solid fa-file-lines"></i> Schemes
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.form-builder.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.form-builder.index') }}">
                                    <i class="metismenu-icon fa-solid fa-file-pen"></i> Application Forms
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.schemes.*') && request('type') === 'proposed' ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.schemes.index', ['type' => 'proposed']) }}">
                                    <i class="metismenu-icon fa-solid fa-flag"></i> Proposed (CM)
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.stats.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.stats.index') }}">
                                    <i class="metismenu-icon fa-solid fa-chart-simple"></i> Stats
                                </a>
                            </li>
                        @endif
                        <li class="{{ request()->routeIs('admin.applications.*') ? 'mm-active' : '' }}">
                            <a href="{{ route('admin.applications.index') }}">
                                <i class="metismenu-icon fa-solid fa-folder"></i> Applications
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('admin.interests.*') ? 'mm-active' : '' }}">
                            <a href="{{ route('admin.interests.index') }}">
                                <i class="metismenu-icon fa-solid fa-bell"></i> Interests
                            </a>
                        </li>
                        @if($canViewCm)
                            <li class="{{ request()->routeIs('admin.cm-dashboard.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.cm-dashboard.index') }}">
                                    <i class="metismenu-icon fa-solid fa-chart-line"></i> CM Dashboard
                                </a>
                            </li>
                        @endif
                        @if($user?->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'District Officer', 'District Deputy Director', 'Division Director', 'Field Officer', 'Accounts Officer', 'Distribution Officer']))
                            <li class="{{ request()->routeIs('admin.wheat-mis.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.wheat-mis.index') }}">
                                    <i class="metismenu-icon fa-solid fa-wheat-awn"></i> Wheat Seed MIS
                                </a>
                            </li>
                        @endif
                        @if($isExecutive)
                            <li class="{{ request()->routeIs('admin.users.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.users.index') }}">
                                    <i class="metismenu-icon fa-solid fa-users"></i> Users & Hierarchy
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.roles.matrix') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.roles.matrix') }}">
                                    <i class="metismenu-icon fa-solid fa-shield-halved"></i> Roles & Matrix
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.audit-logs.*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin.audit-logs.index') }}">
                                    <i class="metismenu-icon fa-solid fa-clock-rotate-left"></i> Access Audit Logs
                                </a>
                            </li>
                        @endif
                        <li class="app-sidebar__heading">Account</li>
                        <li>
                            <form action="{{ route('admin.logout') }}" method="POST">
                                @csrf
                                <a href="#" onclick="event.preventDefault(); this.closest('form').submit();">
                                    <i class="metismenu-icon fa-solid fa-right-from-bracket"></i> Logout
                                </a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="app-main__outer">
            <div class="app-main__inner">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
            <div class="app-wrapper-footer">
                <div class="app-footer">
                    <div class="app-footer__inner">
                        <div class="app-footer-left">
                            <span class="text-muted">{!! __('portal.powered_by', ['year' => date('Y')]) !!}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@stack('modals')

<script defer src="{{ asset('admin-assets/assets/scripts/vendors.46b0e993b2652496c5e9.js') }}"></script>
<script defer src="{{ asset('admin-assets/assets/scripts/scrollbar.46b0e993b2652496c5e9.js') }}"></script>
<script defer src="{{ asset('admin-assets/assets/scripts/main.46b0e993b2652496c5e9.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Hoist any trapped modals directly to document.body so ArchitectUI's fixed header/sidebar never obscures them
        document.querySelectorAll('.modal').forEach(function (modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    });
</script>
@stack('scripts')
</body>
</html>
