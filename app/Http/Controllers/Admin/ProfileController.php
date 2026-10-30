<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the change password & profile form.
     */
    public function showChangePassword(): View
    {
        $user = Auth::user();

        return view('admin.profile.change-password', compact('user'));
    }

    /**
     * Update the logged-in user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()->min(8)],
        ], [
            'current_password.current_password' => 'The provided current password does not match your existing password.',
            'password.confirmed' => 'New password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters long.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        AccessAuditLog::log('user_password_changed', get_class($user), (string) $user->id, [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Your password has been changed successfully.');
    }
}

