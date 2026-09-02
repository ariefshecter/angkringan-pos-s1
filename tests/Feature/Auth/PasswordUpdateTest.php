<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_own_password(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password', $admin->refresh()->password));
    }

    public function test_kasir_can_update_own_password(): void
    {
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password', $kasir->refresh()->password));
    }

    public function test_correct_password_is_required_to_update_password(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect(route('profile.edit'));
    }
}
