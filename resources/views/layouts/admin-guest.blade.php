<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') — GB ALF Dashboard</title>
    <link href="{{ asset('admin-assets/assets/styles/vendors.46b0e993b2652496c5e9.css') }}" rel="stylesheet">
    <link href="{{ asset('admin-assets/assets/styles/main.46b0e993b2652496c5e9.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{{ asset('admin-assets/css/alf-admin.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="app-container app-theme-white body-tabs-shadow">
    <div class="app-container">
        <div class="h-100 bg-plum-plate alf-login-bg bg-animation">
            <div class="d-flex h-100 justify-content-center align-items-center py-4">
                <div class="mx-auto col-sm-10 col-md-8 col-lg-6 col-xl-5">
                    @yield('content')
                    <div class="text-center mt-4">
                        <p class="text-white mb-0" style="font-size: 0.9rem; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            © {{ date('Y') }} Government of Gilgit-Baltistan
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script defer src="{{ asset('admin-assets/assets/scripts/vendors.46b0e993b2652496c5e9.js') }}"></script>
<script defer src="{{ asset('admin-assets/assets/scripts/main.46b0e993b2652496c5e9.js') }}"></script>
@stack('scripts')
</body>
</html>
