<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_logout_succeeds_for_authenticated_user(): void
    {
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_admin_logout_handles_expired_csrf_without_419(): void
    {
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        // Force CSRF validation on this request to verify 419 handling
        $this->app['env'] = 'production';

        $response = $this->actingAs($user)->post(route('admin.logout'), [
            '_token' => 'expired-or-invalid-token',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('info', 'You have been logged out.');
        $this->assertGuest();
    }

    public function test_admin_logout_get_redirects_cleanly_without_405(): void
    {
        $guestRes = $this->get('/admin/logout');
        $guestRes->assertRedirect(route('admin.login'));

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        $authRes = $this->actingAs($user)->get('/admin/logout');
        $authRes->assertRedirect(route('admin.dashboard'));
    }
}
