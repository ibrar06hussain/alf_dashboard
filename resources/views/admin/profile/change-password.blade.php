@extends('layouts.admin')

@section('title', 'Change Password')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Change Password',
    'subtitle' => 'Update your account login credentials securely.',
    'icon' => 'fa-solid fa-key',
])

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card alf-panel shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-shield-halved me-2 text-primary"></i> Security & Password</span>
                <span class="badge bg-secondary">{{ $user->roles->pluck('name')->first() ?? 'User' }}</span>
            </div>
            <div class="card-body p-4">
                {{-- Account summary card --}}
                <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 20px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">{{ $user->name }}</div>
                        <div class="small text-muted">{{ $user->email }} · {{ $user->district?->name ?? 'All Districts' }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.profile.password.update') }}">
                    @csrf
                    @method('PUT')

                    {{-- Current Password --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="current_password">
                            Current Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input 
                                type="password" 
                                class="form-control @error('current_password') is-invalid @enderror" 
                                id="current_password" 
                                name="current_password" 
                                required 
                                autocomplete="current-password"
                                placeholder="Enter current password"
                            >
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="current_password" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        @error('current_password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-3 text-muted">

                    {{-- New Password --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="new_password">
                            New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-key text-muted"></i></span>
                            <input 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                id="new_password" 
                                name="password" 
                                required 
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Enter at least 8 characters"
                            >
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="new_password" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">Password must be at least 8 characters long.</div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Confirm New Password --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="password_confirmation">
                            Confirm New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-check-double text-muted"></i></span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password_confirmation" 
                                name="password_confirmation" 
                                required 
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Re-enter new password"
                            >
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="password_confirmation" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2">
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-light">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-pwd').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
});
</script>
@endsection

