<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Kasir;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KasirManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_and_update_kasir(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->get(route('admin.kasir.index'))
            ->assertOk()
            ->assertSee('Kelola Kasir')
            ->assertSee('Tambah Kasir');

        $this->get(route('admin.kasir.create'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);

        $this->post(route('admin.kasir.store'), [
            'name' => 'Kasir Satu',
            'username' => 'kasir-satu',
            'email' => 'kasir.satu@example.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.kasir.index'));

        $kasir = Kasir::query()->where('username', 'kasir-satu')->firstOrFail();
        $this->assertTrue(Hash::check('password-baru', $kasir->password));

        $this->get(route('admin.kasir.edit', $kasir))
            ->assertOk()
            ->assertSee('kasir-satu')
            ->assertDontSee('name="password"', false);

        $this->put(route('admin.kasir.update', $kasir), [
            'name' => 'Kasir Utama',
            'username' => 'kasir-utama',
            'email' => 'kasir.utama@example.com',
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.kasir.index'));

        $this->assertDatabaseHas('kasir', [
            'id' => $kasir->id,
            'name' => 'Kasir Utama',
            'username' => 'kasir-utama',
            'email' => 'kasir.utama@example.com',
            'is_active' => true,
        ]);
    }

    public function test_kasir_username_must_be_unique_across_admin_and_kasir_tables(): void
    {
        $admin = Admin::factory()->create(['username' => 'pemilik']);
        $existing = Kasir::factory()->create(['username' => 'kasir-lama']);

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin']);

        $this->from(route('admin.kasir.create'))->post(route('admin.kasir.store'), [
            'name' => 'Kasir Baru',
            'username' => $admin->username,
            'email' => 'kasir.baru@example.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
            'is_active' => '1',
        ])->assertSessionHasErrors('username')->assertRedirect(route('admin.kasir.create'));

        $this->from(route('admin.kasir.edit', $existing))->put(route('admin.kasir.update', $existing), [
            'name' => $existing->name,
            'username' => $admin->username,
            'email' => $existing->email,
            'is_active' => '1',
        ])->assertSessionHasErrors('username')->assertRedirect(route('admin.kasir.edit', $existing));

        $this->assertSame('kasir-lama', $existing->fresh()->username);
    }

    public function test_admin_can_deactivate_and_reactivate_kasir(): void
    {
        $admin = Admin::factory()->create();
        $kasir = Kasir::factory()->create(['is_active' => true]);

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->patch(route('admin.kasir.toggle', $kasir))
            ->assertRedirect();

        $this->assertFalse($kasir->fresh()->is_active);

        $this->patch(route('admin.kasir.toggle', $kasir))->assertRedirect();

        $this->assertTrue($kasir->fresh()->is_active);
    }

    public function test_admin_can_reset_kasir_password(): void
    {
        $admin = Admin::factory()->create();
        $kasir = Kasir::factory()->create();
        $oldPassword = $kasir->password;

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->patch(route('admin.kasir.reset-password', $kasir), [
                'password' => 'password-pengganti',
                'password_confirmation' => 'password-pengganti',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $kasir->refresh();
        $this->assertNotSame($oldPassword, $kasir->password);
        $this->assertTrue(Hash::check('password-pengganti', $kasir->password));
    }

    public function test_kasir_can_not_access_or_modify_kasir_accounts(): void
    {
        $actor = Kasir::factory()->create();
        $target = Kasir::factory()->create();

        $this->activateDevice()->actingAs($actor, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.kasir.index'))->assertForbidden();
        $this->post(route('admin.kasir.store'), [])->assertForbidden();
        $this->put(route('admin.kasir.update', $target), [])->assertForbidden();
        $this->patch(route('admin.kasir.toggle', $target))->assertForbidden();
        $this->patch(route('admin.kasir.reset-password', $target), [])->assertForbidden();
    }

    public function test_kasir_can_view_real_time_menu_stock_without_mutation_controls(): void
    {
        $cashier = Kasir::factory()->create();
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman-stock-kasir']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Teh',
            'slug' => 'es-teh-stock-kasir',
            'price' => 4000,
            'stock' => 10,
            'reserved_stock' => 3,
            'is_available' => true,
        ]);

        $this->activateDevice()->actingAs($cashier, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.stocks.index'))
            ->assertOk()
            ->assertSee('Stok Menu')
            ->assertSee('Es Teh')
            ->assertSee('Stok tersedia')
            ->assertSee('7')
            ->assertSee('Stok diperbarui otomatis setiap 2 detik')
            ->assertSee(route('admin.stocks.feed'), false)
            ->assertDontSee('Tambah menu')
            ->assertDontSee('Edit')
            ->assertDontSee('Hapus');

        $this->get(route('admin.stocks.feed'))
            ->assertOk()
            ->assertJsonPath('menus.0.id', $menu->id)
            ->assertJsonPath('menus.0.stock', 10)
            ->assertJsonPath('menus.0.reserved_stock', 3)
            ->assertJsonPath('menus.0.available_stock', 7);

        $this->get(route('admin.menus.index'))->assertForbidden();
        $this->post(route('admin.menus.store'), [])->assertForbidden();
        $this->put(route('admin.menus.update', $menu), [])->assertForbidden();
        $this->delete(route('admin.menus.destroy', $menu))->assertForbidden();
    }

    public function test_guest_is_redirected_from_all_kasir_management_routes(): void
    {
        $target = Kasir::factory()->create();

        $this->get(route('admin.kasir.index'))->assertRedirect(route('login'));
        $this->post(route('admin.kasir.store'), [])->assertRedirect(route('login'));
        $this->put(route('admin.kasir.update', $target), [])->assertRedirect(route('login'));
        $this->patch(route('admin.kasir.toggle', $target))->assertRedirect(route('login'));
        $this->patch(route('admin.kasir.reset-password', $target), [])->assertRedirect(route('login'));
    }

    public function test_admin_navigation_contains_kasir_management_but_kasir_navigation_does_not(): void
    {
        $admin = Admin::factory()->create();
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.kasir.index'), false)
            ->assertSee('Kelola Kasir');

        $this->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.kasir.index'), false)
            ->assertDontSee('Kelola Kasir');
    }
}
