<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);
    }

    public function test_guest_cannot_access_change_password_page(): void
    {
        $response = $this->get(route('admin.profile.password.show'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_user_can_view_change_password_page(): void
    {
        $user = User::where('email', 'admin@alf.gb.gov.pk')->first();

        $response = $this->actingAs($user)->get(route('admin.profile.password.show'));
        $response->assertStatus(200);
        $response->assertSee('Change Password');
        $response->assertSee($user->email);
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::where('email', 'admin@alf.gb.gov.pk')->first();
        $oldHash = $user->password;

        $response = $this->actingAs($user)->put(route('admin.profile.password.update'), [
            'current_password' => 'wrong-current-password',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertEquals($oldHash, $user->fresh()->password);
    }

    public function test_user_cannot_change_password_with_mismatched_confirmation(): void
    {
        $user = User::where('email', 'admin@alf.gb.gov.pk')->first();
        $oldHash = $user->password;

        $response = $this->actingAs($user)->put(route('admin.profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals($oldHash, $user->fresh()->password);
    }

    public function test_user_can_successfully_change_password(): void
    {
        $user = User::where('email', 'admin@alf.gb.gov.pk')->first();

        $response = $this->actingAs($user)->put(route('admin.profile.password.update'), [
            'current_password' => 'password',
            'password' => 'brand-new-password-2026',
            'password_confirmation' => 'brand-new-password-2026',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('brand-new-password-2026', $user->fresh()->password));
    }
}

