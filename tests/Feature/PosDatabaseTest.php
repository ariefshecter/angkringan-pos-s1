<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_entities_can_be_related(): void
    {
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

        $cashier = User::factory()->create();
        $transaction = Transaction::query()->create([
            'order_number' => 'ORD-TEST-001',
            'handled_by' => $cashier->id,
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
        $this->assertTrue($transaction->handler->is($cashier));
        $this->assertSame('Nasi Bakar', $transaction->items->first()->menu_name);
    }

    public function test_database_seeder_creates_admin_and_categories(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@angkringans1.test',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('categories', 3);
    }
}
