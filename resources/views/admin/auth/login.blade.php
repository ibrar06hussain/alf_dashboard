@extends('layouts.admin-guest')

@section('title', 'Admin Login')

@section('content')
<div class="card border-0" style="border-radius: 24px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
    <div class="card-body p-5">
        <div class="alf-login-brand mb-5">
            <a href="{{ url('/') }}" class="alf-text-logo alf-text-logo--lg" aria-label="ALF Gilgit Baltistan">
                <span class="alf-logo-primary">ALF Gilgit</span><span class="alf-logo-secondary"> Baltistan</span>
            </a>
            <h1 class="fw-bold text-dark mb-2 mt-3" style="font-size: 1.35rem;">Admin Panel</h1>
            <p class="text-muted fs-6 mb-0">Sign in to manage schemes &amp; applications</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                       class="form-control form-control-lg @error('email') is-invalid @enderror"
                       placeholder="admin@alf.gb.gov.pk" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password"
                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                       placeholder="Password" required>
            </div>
            <div class="form-check mb-4">
                <input name="remember" id="remember" type="checkbox" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" class="form-check-label">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                Sign In
            </button>
        </form>
    </div>
</div>
@endsection
