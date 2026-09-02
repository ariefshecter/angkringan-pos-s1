<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Kasir;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_tables_use_the_thesis_names(): void
    {
        foreach (['admin', 'kasir', 'user', 'kategori', 'menu', 'transaksi', 'detail_transaksi', 'laporan'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ditemukan.");
        }

        foreach (['users', 'categories', 'menus', 'transactions', 'transaction_items'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tabel lama {$table} masih tersedia.");
        }

        $this->assertTrue(Schema::hasColumns('transaksi', [
            'user_session_id',
            'admin_id',
            'kasir_id',
            'device_id',
            'customer_name',
            'payment_status',
            'order_status',
        ]));
    }

    public function test_pos_entities_can_be_related(): void
    {
        $customer = Customer::query()->create([
            'session_id' => 'guest-session-001',
            'name' => 'Pelanggan',
        ]);
        $category = Category::query()->create([
            'name' => 'Makanan',
            'slug' => 'makanan',
        ]);

        $menu = $category->menus()->create([
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar',
            'price' => 10000,
            'stock' => 10,
            'reserved_stock' => 2,
        ]);

        $cashier = Kasir::factory()->create();
        $transaction = Transaction::query()->create([
            'order_number' => 'ORD-TEST-001',
            'user_session_id' => $customer->session_id,
            'kasir_id' => $cashier->id,
            'customer_name' => 'Pelanggan',
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cash',
        ]);

        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => $menu->price,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $this->assertTrue($menu->category->is($category));
        $this->assertSame(8, $menu->available_stock);
        $this->assertTrue($transaction->customer->is($customer));
        $this->assertTrue($transaction->kasir->is($cashier));
        $this->assertNull($transaction->admin);
        $this->assertSame('Nasi Bakar', $transaction->items->first()->menu_name);
    }

    public function test_transaction_can_not_have_both_admin_and_kasir_actors(): void
    {
        $this->expectException(ValidationException::class);

        Transaction::query()->create([
            'order_number' => 'ORD-TEST-BOTH-ACTORS',
            'user_session_id' => Customer::query()->create([
                'session_id' => 'guest-session-both-actors',
                'name' => 'Pelanggan',
            ])->session_id,
            'admin_id' => Admin::factory()->create()->id,
            'kasir_id' => Kasir::factory()->create()->id,
            'customer_name' => 'Pelanggan',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
        ]);
    }

    public function test_report_period_is_unique_and_belongs_to_admin(): void
    {
        $admin = Admin::factory()->create();
        $report = Report::query()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'total_revenue' => 500000,
            'transaction_count' => 25,
            'admin_id' => $admin->id,
        ]);

        $this->assertTrue($report->admin->is($admin));
        $this->assertSame('2026-08-01', $report->period_start->toDateString());
        $this->assertSame('2026-08-31', $report->period_end->toDateString());
        $this->assertDatabaseHas('laporan', [
            'total_revenue' => 500000,
            'transaction_count' => 25,
        ]);

        $this->expectException(QueryException::class);
        Report::query()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'total_revenue' => 600000,
            'transaction_count' => 30,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_database_seeder_creates_staff_categories_and_devices(): void
    {
        $this->seed();

        $this->assertDatabaseHas('admin', [
            'username' => 'admin',
            'email' => 'admin@angkringans1.test',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('kasir', [
            'username' => 'kasir',
            'email' => 'kasir@angkringans1.test',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('kategori', 3);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'name' => 'Device Pelanggan',
            'type' => Device::TYPE_KIOSK,
        ]);
    }
}
