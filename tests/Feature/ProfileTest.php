<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profile_page_is_displayed_without_account_deletion(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertDontSee('Delete Account');
    }

    public function test_kasir_profile_information_can_be_updated(): void
    {
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->patch(route('profile.update'), [
                'name' => 'Kasir Utama',
                'username' => 'kasir-utama',
                'email' => 'kasir.utama@example.com',
            ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $kasir->refresh();
        $this->assertSame('Kasir Utama', $kasir->name);
        $this->assertSame('kasir-utama', $kasir->username);
        $this->assertSame('kasir.utama@example.com', $kasir->email);
    }

    public function test_username_must_be_unique_across_admin_and_kasir_tables(): void
    {
        $admin = Admin::factory()->create(['username' => 'pemilik']);
        $kasir = Kasir::factory()->create(['username' => 'kasir-awal']);

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $kasir->name,
                'username' => $admin->username,
                'email' => $kasir->email,
            ])->assertSessionHasErrors('username')->assertRedirect(route('profile.edit'));

        $this->assertSame('kasir-awal', $kasir->refresh()->username);
    }

    public function test_profile_deletion_route_is_disabled(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($admin->fresh());
    }
}
