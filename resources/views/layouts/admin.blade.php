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
    $isSuperAdmin = $user?->hasRole('Super Admin');
    $isSecretary = $user?->hasRole('Secretary');
    $canManageSchemes = ! $isSecretary && $user?->hasAnyRole(['Super Admin', 'Director Agriculture', 'Director Livestock', 'Director Fisheries']);
    $canViewCm = $isSecretary || $user?->hasAnyRole(['Super Admin', 'Director Agriculture', 'Director Livestock', 'Director Fisheries', 'Division Director']);
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
                            <div class="widget-content-right header-btn-lg d-flex align-items-center gap-2">
                                <a href="{{ route('admin.profile.password.show') }}" class="btn btn-sm btn-outline-secondary" title="Change Password">
                                    <i class="fa-solid fa-key me-1"></i> Change Password
                                </a>
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
        <div class="alf-sidebar-backdrop" id="alfSidebarBackdrop"></div>
        <div class="app-sidebar sidebar-shadow">
            <div class="app-header__logo">
                <a href="{{ route('admin.dashboard') }}" class="alf-text-logo" aria-label="ALF Gilgit Baltistan">
                    <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
                </a>
                <div class="header__pane ms-auto">
                    <div>
                        <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar" title="Toggle Sidebar">
                            <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="scrollbar-sidebar">
                <div class="app-sidebar__inner">
                    @php
                        $canViewGbpay = $user?->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries', 'Accounts Officer']);
                        $canViewWheatMis = $user?->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'District Officer', 'District Deputy Director', 'Division Director', 'Field Officer', 'Accounts Officer', 'Distribution Officer']);
                        $canViewStock = $user?->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries', 'District Officer', 'District Deputy Director', 'Division Director', 'Distribution Officer']);

                        $isAnalyticsActive = request()->routeIs('admin.cm-dashboard.*', 'admin.wheat-mis.*', 'admin.stats.*');
                        $isApplicationsActive = request()->routeIs('admin.applications.*', 'admin.interests.*', 'admin.gbpay.*');
                        $isSchemesActive = request()->routeIs('admin.schemes.*', 'admin.challan-templates.*', 'admin.form-builder.*');
                        $isStockActive = request()->routeIs('admin.stock.*');
                        $isDirectoriesActive = request()->routeIs('admin.organizations.*', 'admin.departments.*', 'admin.districts.*');
                        $isCmsActive = request()->routeIs('admin.carousel.*', 'admin.media.*', 'admin.site-info.*', 'admin.sms-templates.*');
                        $isSecurityActive = request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.audit-logs.*');
                        $isAccountActive = request()->routeIs('admin.profile.password.*');
                    @endphp
                    <ul class="vertical-nav-menu" id="alfSidebarNav">
                        <li class="app-sidebar__heading">Overview</li>
                        <li class="{{ request()->routeIs('admin.dashboard') ? 'mm-active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'mm-active' : '' }}">
                                <i class="metismenu-icon fa-solid fa-gauge-high"></i> Dashboard
                            </a>
                        </li>

                        @if($canViewCm || $canViewWheatMis || $canManageSchemes)
                            <li class="alf-nav-group {{ $isAnalyticsActive ? 'mm-active' : '' }}">
                                <a href="#" data-alf-collapse="true" aria-expanded="{{ $isAnalyticsActive ? 'true' : 'false' }}">
                                    <i class="metismenu-icon fa-solid fa-chart-line"></i>
                                    MIS &amp; Analytics
                                    <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                                </a>
                                <ul class="mm-collapse {{ $isAnalyticsActive ? 'mm-show' : '' }}">
                                    @if($canViewCm)
                                        <li class="{{ request()->routeIs('admin.cm-dashboard.*') ? 'mm-active' : '' }}">
                                            <a href="{{ route('admin.cm-dashboard.index') }}" class="{{ request()->routeIs('admin.cm-dashboard.*') ? 'mm-active' : '' }}">
                                                <i class="sub-nav-icon fa-solid fa-building-columns"></i> CM Dashboard
                                            </a>
                                        </li>
                                    @endif
                                    @if($canViewWheatMis)
                                        <li class="{{ request()->routeIs('admin.wheat-mis.*') ? 'mm-active' : '' }}">
                                            <a href="{{ route('admin.wheat-mis.index') }}" class="{{ request()->routeIs('admin.wheat-mis.*') ? 'mm-active' : '' }}">
                                                <i class="sub-nav-icon fa-solid fa-wheat-awn"></i> Wheat Seed MIS
                                            </a>
                                        </li>
                                    @endif
                                    @if($canManageSchemes)
                                        <li class="{{ request()->routeIs('admin.stats.*') ? 'mm-active' : '' }}">
                                            <a href="{{ route('admin.stats.index') }}" class="{{ request()->routeIs('admin.stats.*') ? 'mm-active' : '' }}">
                                                <i class="sub-nav-icon fa-solid fa-chart-simple"></i> Department Stats
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        <li class="app-sidebar__heading">Operations</li>

                        {{-- Collapsible Group: Applications & Citizen Requests --}}
                        <li class="alf-nav-group {{ $isApplicationsActive ? 'mm-active' : '' }}">
                            <a href="#" data-alf-collapse="true" aria-expanded="{{ $isApplicationsActive ? 'true' : 'false' }}">
                                <i class="metismenu-icon fa-solid fa-folder-open"></i>
                                Applications &amp; Billing
                                <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                            </a>
                            <ul class="mm-collapse {{ $isApplicationsActive ? 'mm-show' : '' }}">
                                <li class="{{ request()->routeIs('admin.applications.*') ? 'mm-active' : '' }}">
                                    <a href="{{ route('admin.applications.index') }}" class="{{ request()->routeIs('admin.applications.*') ? 'mm-active' : '' }}">
                                        <i class="sub-nav-icon fa-solid fa-folder"></i> Applications Queue
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('admin.interests.*') ? 'mm-active' : '' }}">
                                    <a href="{{ route('admin.interests.index') }}" class="{{ request()->routeIs('admin.interests.*') ? 'mm-active' : '' }}">
                                        <i class="sub-nav-icon fa-solid fa-bell"></i> Citizen Interests
                                    </a>
                                </li>
                                @if($canViewGbpay)
                                    <li class="{{ request()->routeIs('admin.gbpay.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.gbpay.index') }}" class="{{ request()->routeIs('admin.gbpay.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-money-check-dollar"></i> GBPay Gateway (PSID)
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>

                        {{-- Collapsible Group: Schemes & Forms --}}
                        @if($canManageSchemes)
                            <li class="alf-nav-group {{ $isSchemesActive ? 'mm-active' : '' }}">
                                <a href="#" data-alf-collapse="true" aria-expanded="{{ $isSchemesActive ? 'true' : 'false' }}">
                                    <i class="metismenu-icon fa-solid fa-file-signature"></i>
                                    Schemes &amp; Forms
                                    <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                                </a>
                                <ul class="mm-collapse {{ $isSchemesActive ? 'mm-show' : '' }}">
                                    <li class="{{ request()->routeIs('admin.schemes.*') && request('type') !== 'proposed' ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.schemes.index') }}" class="{{ request()->routeIs('admin.schemes.*') && request('type') !== 'proposed' ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-file-lines"></i> Running Schemes
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.schemes.*') && request('type') === 'proposed' ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.schemes.index', ['type' => 'proposed']) }}" class="{{ request()->routeIs('admin.schemes.*') && request('type') === 'proposed' ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-flag"></i> Proposed (CM)
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.form-builder.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.form-builder.index') }}" class="{{ request()->routeIs('admin.form-builder.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-file-pen"></i> Application Forms
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.challan-templates.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.challan-templates.index') }}" class="{{ request()->routeIs('admin.challan-templates.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-receipt"></i> Challan Templates
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        {{-- Collapsible Group: Stock & Logistics --}}
                        @if($canViewStock)
                            <li class="alf-nav-group {{ $isStockActive ? 'mm-active' : '' }}">
                                <a href="#" data-alf-collapse="true" aria-expanded="{{ $isStockActive ? 'true' : 'false' }}">
                                    <i class="metismenu-icon fa-solid fa-boxes-stacked"></i>
                                    Stock &amp; Logistics
                                    <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                                </a>
                                <ul class="mm-collapse {{ $isStockActive ? 'mm-show' : '' }}">
                                    <li class="{{ request()->routeIs('admin.stock.index') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.index') }}" class="{{ request()->routeIs('admin.stock.index') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-cubes"></i> Stock Overview
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.demand') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.demand') }}" class="{{ request()->routeIs('admin.stock.demand') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-chart-pie"></i> Demand vs Stock
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.centers*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.centers') }}" class="{{ request()->routeIs('admin.stock.centers*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-warehouse"></i> Collection Centers
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.receipts*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.receipts') }}" class="{{ request()->routeIs('admin.stock.receipts*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-truck-ramp-box"></i> Stock In (Received)
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.disbursements*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.disbursements') }}" class="{{ request()->routeIs('admin.stock.disbursements*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-hand-holding-dollar"></i> Stock Out (Disbursed)
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.transfers*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.transfers') }}" class="{{ request()->routeIs('admin.stock.transfers*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-arrow-right-arrow-left"></i> Stock Transfers
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.stock.damages*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.stock.damages') }}" class="{{ request()->routeIs('admin.stock.damages*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-triangle-exclamation"></i> Wasted / Damaged
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        {{-- Collapsible Group: Directories & Organizations --}}
                        <li class="alf-nav-group {{ $isDirectoriesActive ? 'mm-active' : '' }}">
                            <a href="#" data-alf-collapse="true" aria-expanded="{{ $isDirectoriesActive ? 'true' : 'false' }}">
                                <i class="metismenu-icon fa-solid fa-people-group"></i>
                                Directories &amp; Regions
                                <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                            </a>
                            <ul class="mm-collapse {{ $isDirectoriesActive ? 'mm-show' : '' }}">
                                <li class="{{ request()->routeIs('admin.organizations.*') ? 'mm-active' : '' }}">
                                    <a href="{{ route('admin.organizations.index') }}" class="{{ request()->routeIs('admin.organizations.*') ? 'mm-active' : '' }}">
                                        <i class="sub-nav-icon fa-solid fa-building-wheat"></i> Organizations (GB)
                                    </a>
                                </li>
                                @if($isSuperAdmin)
                                    <li class="{{ request()->routeIs('admin.departments.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.departments.index') }}" class="{{ request()->routeIs('admin.departments.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-landmark"></i> Departments
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.districts.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.districts.index') }}" class="{{ request()->routeIs('admin.districts.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-location-dot"></i> Districts
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>

                        @if($isSuperAdmin)
                            <li class="app-sidebar__heading">Administration</li>

                            {{-- Collapsible Group: Website Content & SMS --}}
                            <li class="alf-nav-group {{ $isCmsActive ? 'mm-active' : '' }}">
                                <a href="#" data-alf-collapse="true" aria-expanded="{{ $isCmsActive ? 'true' : 'false' }}">
                                    <i class="metismenu-icon fa-solid fa-globe"></i>
                                    Portal CMS &amp; SMS
                                    <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                                </a>
                                <ul class="mm-collapse {{ $isCmsActive ? 'mm-show' : '' }}">
                                    <li class="{{ request()->routeIs('admin.carousel.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.carousel.index') }}" class="{{ request()->routeIs('admin.carousel.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-images"></i> Carousel Slides
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.media.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.media.edit') }}" class="{{ request()->routeIs('admin.media.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-photo-film"></i> Site Media
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.site-info.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.site-info.edit') }}" class="{{ request()->routeIs('admin.site-info.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-address-card"></i> Site Information
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.sms-templates.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.sms-templates.index') }}" class="{{ request()->routeIs('admin.sms-templates.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-comment-sms"></i> SMS Gateway
                                        </a>
                                    </li>
                                </ul>
                            </li>

                            {{-- Collapsible Group: Users, Roles & Audit --}}
                            <li class="alf-nav-group {{ $isSecurityActive ? 'mm-active' : '' }}">
                                <a href="#" data-alf-collapse="true" aria-expanded="{{ $isSecurityActive ? 'true' : 'false' }}">
                                    <i class="metismenu-icon fa-solid fa-user-shield"></i>
                                    Users &amp; Security
                                    <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                                </a>
                                <ul class="mm-collapse {{ $isSecurityActive ? 'mm-show' : '' }}">
                                    <li class="{{ request()->routeIs('admin.users.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-users"></i> Users &amp; Hierarchy
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.roles.matrix') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.roles.matrix') }}" class="{{ request()->routeIs('admin.roles.matrix') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-shield-halved"></i> Roles &amp; Matrix
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('admin.audit-logs.*') ? 'mm-active' : '' }}">
                                        <a href="{{ route('admin.audit-logs.index') }}" class="{{ request()->routeIs('admin.audit-logs.*') ? 'mm-active' : '' }}">
                                            <i class="sub-nav-icon fa-solid fa-clock-rotate-left"></i> Access Audit Logs
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <li class="app-sidebar__heading">Account</li>
                        <li class="alf-nav-group {{ $isAccountActive ? 'mm-active' : '' }}">
                            <a href="#" data-alf-collapse="true" aria-expanded="{{ $isAccountActive ? 'true' : 'false' }}">
                                <i class="metismenu-icon fa-solid fa-circle-user"></i>
                                Account Settings
                                <i class="metismenu-state-icon fa-solid fa-chevron-down"></i>
                            </a>
                            <ul class="mm-collapse {{ $isAccountActive ? 'mm-show' : '' }}">
                                <li class="{{ request()->routeIs('admin.profile.password.*') ? 'mm-active' : '' }}">
                                    <a href="{{ route('admin.profile.password.show') }}" class="{{ request()->routeIs('admin.profile.password.*') ? 'mm-active' : '' }}">
                                        <i class="sub-nav-icon fa-solid fa-key"></i> Change Password
                                    </a>
                                </li>
                                <li>
                                    <form action="{{ route('admin.logout') }}" method="POST">
                                        @csrf
                                        <a href="#" onclick="event.preventDefault(); this.closest('form').submit();">
                                            <i class="sub-nav-icon fa-solid fa-right-from-bracket text-danger"></i> Logout
                                        </a>
                                    </form>
                                </li>
                            </ul>
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
        // 1. Hoist any trapped modals directly to document.body so ArchitectUI's fixed header/sidebar never obscures them
        document.querySelectorAll('.modal').forEach(function (modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        // 2. Desktop / Laptop Sidebar Collapse Toggle (.close-sidebar-btn) + Persistence
        var appContainer = document.querySelector('.app-container');
        var closeSidebarBtns = document.querySelectorAll('.close-sidebar-btn');
        var sidebarBackdrop = document.getElementById('alfSidebarBackdrop');

        function applyDesktopSidebarPref() {
            if (!appContainer) return;
            if (window.innerWidth >= 992) {
                var savedCollapsed = localStorage.getItem('alf_sidebar_collapsed') === '1';
                appContainer.classList.toggle('closed-sidebar', savedCollapsed);
                closeSidebarBtns.forEach(function (btn) {
                    btn.classList.toggle('is-active', savedCollapsed);
                });
            }
        }

        // Run initially and after ArchitectUI's 400ms startup timer
        applyDesktopSidebarPref();
        setTimeout(applyDesktopSidebarPref, 150);
        setTimeout(applyDesktopSidebarPref, 500);

        closeSidebarBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!appContainer) return;
                var isNowClosed = !appContainer.classList.contains('closed-sidebar');
                appContainer.classList.toggle('closed-sidebar', isNowClosed);
                localStorage.setItem('alf_sidebar_collapsed', isNowClosed ? '1' : '0');
                closeSidebarBtns.forEach(function (b) {
                    b.classList.toggle('is-active', isNowClosed);
                });
                setTimeout(function () {
                    window.dispatchEvent(new Event('resize'));
                }, 320);
            });
        });

        // Keep user's desktop sidebar state intact when window resizes
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                setTimeout(applyDesktopSidebarPref, 20);
            }
        });

        // Close mobile sidebar when tapping outside on the backdrop
        if (sidebarBackdrop && appContainer) {
            sidebarBackdrop.addEventListener('click', function () {
                appContainer.classList.remove('sidebar-mobile-open');
                document.querySelectorAll('.mobile-toggle-nav').forEach(function (b) {
                    b.classList.remove('is-active');
                });
            });
        }

        // 3. Reliable Collapsible Sidebar Groups (works alongside or independent of MetisMenu)
        document.querySelectorAll('#alfSidebarNav a[data-alf-collapse="true"]').forEach(function (toggleLink) {
            toggleLink.addEventListener('click', function (e) {
                e.preventDefault();
                // Wait a microtask to see if MetisMenu handled it; if not, toggle manually
                var parentLi = toggleLink.closest('li.alf-nav-group');
                var subMenu = parentLi ? parentLi.querySelector(':scope > ul.mm-collapse') : null;
                if (!parentLi || !subMenu) return;

                var beforeShow = subMenu.classList.contains('mm-show');
                setTimeout(function () {
                    var afterShow = subMenu.classList.contains('mm-show') || subMenu.classList.contains('mm-collapsing');
                    if (beforeShow === afterShow) {
                        var expand = !beforeShow;
                        parentLi.classList.toggle('mm-active', expand);
                        subMenu.classList.toggle('mm-show', expand);
                        toggleLink.setAttribute('aria-expanded', expand ? 'true' : 'false');
                        if (window.sidebarScrollbar && window.sidebarScrollbar.update) {
                            window.sidebarScrollbar.update();
                        }
                    }
                }, 30);
            });
        });
    });
</script>
@stack('scripts')
</body>
</html>
