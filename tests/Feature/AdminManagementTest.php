<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Kasir;
use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_categories(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Paket Hemat',
            'sort_order' => 4,
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'paket-hemat')->firstOrFail();
        $this->activateDevice()->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Paket Spesial',
            'sort_order' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('kategori', ['slug' => 'paket-spesial']);
    }

    public function test_category_status_reflects_orderable_menu_stock(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::query()->create([
            'name' => 'Camilan',
            'slug' => 'camilan',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kentang Goreng',
            'slug' => 'kentang-goreng',
            'price' => 5000,
            'stock' => 0,
            'is_available' => true,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Stok habis')
            ->assertDontSee('>Aktif<', false);

        $menu->update(['stock' => 10]);

        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('>Aktif<', false)
            ->assertDontSee('Stok habis');

        $category->update(['is_active' => false]);

        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Nonaktif')
            ->assertDontSee('>Aktif<', false);
    }

    public function test_admin_can_create_menu_with_image(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);

        $this->activateDevice()->actingAs($admin)->post(route('admin.menus.store'), [
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'description' => 'Nasi bakar hangat.',
            'price' => 10000,
            'stock' => 20,
            'is_available' => 1,
            'image' => UploadedFile::fake()->image('nasi-bakar.jpg'),
        ])->assertRedirect(route('admin.menus.index'));

        $menu = Menu::query()->where('slug', 'nasi-bakar')->firstOrFail();
        Storage::disk('public')->assertExists($menu->image_path);
    }

    public function test_cashier_can_not_access_admin_management(): void
    {
        $cashier = Kasir::factory()->create();

        $this->activateDevice()->actingAs($cashier, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.categories.index'))->assertForbidden();
        $this->get(route('admin.menus.index'))->assertForbidden();
    }

    public function test_admin_can_not_reduce_stock_below_active_reservations(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Sate Usus',
            'slug' => 'sate-usus',
            'price' => 3000,
            'stock' => 10,
            'reserved_stock' => 6,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->put(route('admin.menus.update', $menu), [
                'category_id' => $category->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'stock' => 5,
                'is_available' => 1,
            ])
            ->assertRedirect(route('admin.menus.edit', $menu))
            ->assertSessionHasErrors('stock');

        $this->assertSame(10, $menu->fresh()->stock);
    }

    public function test_menu_with_zero_available_stock_is_shown_as_unavailable(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kentang Goreng',
            'slug' => 'kentang-goreng',
            'price' => 5000,
            'stock' => 0,
            'is_available' => true,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.menus.index'))
            ->assertOk()
            ->assertSee('Kentang Goreng')
            ->assertSee('Tidak tersedia')
            ->assertDontSee('>Tersedia<', false);
    }

    public function test_admin_transaction_pages_have_automatic_live_regions(): void
    {
        $admin = Admin::factory()->create();
        $customer = Customer::query()->create([
            'session_id' => 'dashboard-customer',
            'name' => 'Pelanggan Live',
        ]);
        Transaction::query()->create([
            'order_number' => 'S1-LIVE-DASHBOARD',
            'user_session_id' => $customer->session_id,
            'customer_name' => 'Pelanggan Live',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
            'payment_status' => Transaction::PAYMENT_PENDING,
            'order_status' => Transaction::ORDER_NEW,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="dashboard-live"', false)
            ->assertSee('Dashboard terhubung dan diperbarui otomatis setiap 2 detik')
            ->assertSee('S1-LIVE-DASHBOARD')
            ->assertSee('Pelanggan Live')
            ->assertSee('Konfirmasi & Cetak', false)
            ->assertSee('md:hidden', false)
            ->assertSee('md:block', false)
            ->assertSee("appLiveRegion('#dashboard-live'", false);

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('id="orders-live"', false)
            ->assertSee("appLiveRegion('#orders-live'", false);
    }
}
