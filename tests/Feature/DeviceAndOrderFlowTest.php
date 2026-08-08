<?php

namespace Tests\Feature;

use App\Models\CartReservation;
use App\Models\Category;
use App\Models\Device;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceAndOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_be_activated_with_its_code(): void
    {
        Device::query()->create([
            'name' => 'Device Pelanggan',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => hash('sha256', 'KIOSK-S1-2026'),
            'is_active' => true,
        ]);

        $this->post(route('device.activate.store'), [
            'activation_code' => 'kiosk-s1-2026',
        ])->assertRedirect(route('kiosk.index'))->assertCookie('device_token');
    }

    public function test_unregistered_device_can_not_access_kiosk(): void
    {
        $this->get(route('kiosk.index'))->assertRedirect(route('device.activate'));
    }

    public function test_midtrans_payment_channels_exclude_convenience_stores(): void
    {
        $payments = config('services.midtrans.enabled_payments');
        $midtrans = app(MidtransService::class);

        $this->assertContains('other_qris', $payments);
        $this->assertContains('bca_va', $payments);
        $this->assertSame('Bank Lainnya', config('services.midtrans.bank_channels.other_bank_va'));
        $this->assertSame('permata', config('services.midtrans.core_bank_codes.other_bank_va'));
        $this->assertNotContains('gopay', $payments);
        $this->assertNotContains('dana', $payments);
        $this->assertNotContains('ovo', $payments);
        $this->assertNotContains('shopeepay', $payments);
        $this->assertNotContains('alfamart', $payments);
        $this->assertNotContains('indomaret', $payments);
        $this->assertSame(
            ['other_qris'],
            $midtrans->enabledPaymentsFor(new Transaction(['payment_channel' => 'other_qris']))
        );
        $this->assertSame(
            ['bca_va'],
            $midtrans->enabledPaymentsFor(new Transaction(['payment_channel' => 'bca_va']))
        );
        $this->assertSame(
            ['permata_va'],
            $midtrans->enabledPaymentsFor(new Transaction(['payment_channel' => 'other_bank_va']))
        );
    }

    public function test_kiosk_can_create_qris_only_order(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Teh',
            'slug' => 'es-teh',
            'price' => 5000,
            'stock' => 10,
        ]);
        $this->mock(MidtransService::class)
            ->shouldReceive('createSnapToken')
            ->once()
            ->andReturn('qris-snap-token');

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Siti',
            'payment_method' => 'qris',
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('midtrans', $transaction->payment_method);
        $this->assertSame('other_qris', $transaction->payment_channel);
        $this->assertSame('qris-snap-token', $transaction->midtrans_snap_token);
    }

    public function test_kiosk_can_choose_bank_for_virtual_account_order(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar',
            'price' => 8000,
            'stock' => 10,
        ]);
        $this->mock(MidtransService::class)
            ->shouldReceive('createVirtualAccount')
            ->once()
            ->andReturn([
                'transaction_id' => 'midtrans-va-test-id',
                'payment_type' => 'bank_transfer',
                'bank' => 'bca',
                'va_number' => '123456789012345',
            ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);

        $this->get(route('kiosk.checkout.create'))
            ->assertOk()
            ->assertDontSee('Nomor meja')
            ->assertSee('Transfer Bank')
            ->assertSee('Virtual Account')
            ->assertSee('Bank Lainnya')
            ->assertSee('Transfer antarbank')
            ->assertDontSee('E-Wallet')
            ->assertDontSee('GoPay')
            ->assertDontSee('DANA')
            ->assertSee('Batalkan pesanan')
            ->assertSee('role="dialog"', false)
            ->assertSee('id="cancel-checkout-from-modal"', false)
            ->assertSee('id="discard-checkout-form"', false)
            ->assertSee('data-close-bank-options', false)
            ->assertSee('Gunakan bank ini')
            ->assertSee('checkoutForm.requestSubmit()', false)
            ->assertDontSee('data-channel-tab', false);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Budi',
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'bca_va',
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('midtrans', $transaction->payment_method);
        $this->assertSame('bca_va', $transaction->payment_channel);
        $this->assertNull($transaction->midtrans_snap_token);
        $this->assertSame('bank_transfer', $transaction->midtrans_payment_type);
        $this->assertSame('bca', $transaction->midtrans_bank);
        $this->assertSame('123456789012345', $transaction->midtrans_va_number);

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('BCA Virtual Account')
            ->assertSee('123456789012345')
            ->assertSee('Salin nomor VA')
            ->assertDontSee('id="pay-button"', false);
    }

    public function test_other_bank_uses_permata_virtual_account_with_interbank_instructions(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Sate Usus',
            'slug' => 'sate-usus',
            'price' => 3000,
            'stock' => 10,
        ]);
        $this->mock(MidtransService::class)
            ->shouldReceive('createVirtualAccount')
            ->once()
            ->andReturn([
                'transaction_id' => 'midtrans-other-bank-test-id',
                'payment_type' => 'bank_transfer',
                'bank' => 'permata',
                'va_number' => '890123456789',
            ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Rudi',
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'other_bank_va',
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('other_bank_va', $transaction->payment_channel);
        $this->assertSame('permata', $transaction->midtrans_bank);
        $this->assertSame('Transfer Bank Lainnya (Permata VA)', $transaction->payment_label);

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('Permata Virtual Account')
            ->assertSee('Anda memilih <strong>Bank Lainnya</strong>', false)
            ->assertSee('transfer antarbank')
            ->assertSee('890123456789');
    }

    public function test_kiosk_can_not_choose_an_ewallet_channel(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Jeruk',
            'slug' => 'es-jeruk',
            'price' => 6000,
            'stock' => 10,
        ]);
        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Rina',
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'gopay',
        ])->assertSessionHasErrors('payment_channel');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_kiosk_can_change_item_quantity_using_json_requests(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Sate Usus',
            'slug' => 'sate-usus',
            'price' => 3000,
            'stock' => 10,
        ]);

        $this->withCredentials()
            ->activateDevice(Device::TYPE_KIOSK)
            ->postJson(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('total', 3000);

        $this->patchJson(route('kiosk.cart.update', $menu), ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 3)
            ->assertJsonPath('items.0.subtotal', 9000)
            ->assertJsonPath('total', 9000);
    }

    public function test_kiosk_catalog_endpoint_reflects_admin_menu_changes_without_page_reload(): void
    {
        $category = Category::query()->create([
            'name' => 'Makanan',
            'slug' => 'makanan',
            'is_active' => true,
        ]);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar',
            'price' => 8000,
            'stock' => 10,
            'is_available' => true,
        ]);

        $this->withCredentials()->activateDevice(Device::TYPE_KIOSK);

        $before = $this->getJson(route('kiosk.catalog'))
            ->assertOk()
            ->assertJsonStructure(['version', 'html'])
            ->json();

        $menu->update([
            'name' => 'Nasi Bakar Spesial',
            'price' => 12000,
        ]);

        $after = $this->getJson(route('kiosk.catalog'))
            ->assertOk()
            ->json();

        $this->assertNotSame($before['version'], $after['version']);
        $this->assertStringContainsString('Nasi Bakar Spesial', $after['html']);
        $this->assertStringContainsString('Rp 12.000', $after['html']);
    }

    public function test_kiosk_device_can_create_cash_order_and_admin_can_confirm_it(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar',
            'price' => 10000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Budi',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame(2, $menu->fresh()->reserved_stock);
        $this->assertSame(10, $menu->fresh()->stock);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $transaction))
            ->assertRedirect();

        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->fresh()->payment_status);
        $this->assertSame(8, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
    }

    public function test_kiosk_can_cancel_its_pending_order_and_restore_cart_reservation(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar',
            'price' => 8000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 2]);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Budi',
            'payment_method' => 'cash',
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame(2, $menu->fresh()->reserved_stock);

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('Pilih metode pembayaran lain')
            ->assertSee('Batalkan pesanan');

        $this->post(route('kiosk.orders.cancel', $transaction->order_number))
            ->assertRedirect(route('kiosk.checkout.create'))
            ->assertSessionHas('success', 'Pembayaran dibatalkan. Silakan pilih metode pembayaran lain.')
            ->assertSessionHasInput('customer_name', 'Budi')
            ->assertSessionHas('cart', [$menu->id => 2]);

        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->fresh()->payment_status);
        $this->assertSame(Transaction::ORDER_CANCELLED, $transaction->fresh()->order_status);
        $this->assertSame(2, $menu->fresh()->reserved_stock);
        $this->assertSame(10, $menu->fresh()->stock);

        $this->get(route('kiosk.checkout.create'))
            ->assertOk()
            ->assertSee('Metode pembayaran')
            ->assertSee('Nasi Bakar');
    }

    public function test_kiosk_can_cancel_pending_order_completely_without_restoring_cart(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Bakar',
            'slug' => 'nasi-bakar-cancel-completely',
            'price' => 8000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 2]);

        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Rina',
            'payment_method' => 'cash',
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame(2, $menu->fresh()->reserved_stock);

        $this->post(route('kiosk.orders.cancel-order', $transaction->order_number))
            ->assertRedirect(route('kiosk.index'))
            ->assertSessionHas('success', 'Pesanan berhasil dibatalkan dan stok telah dikembalikan.')
            ->assertSessionMissing('cart');

        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->fresh()->payment_status);
        $this->assertSame(Transaction::ORDER_CANCELLED, $transaction->fresh()->order_status);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertDatabaseMissing('cart_reservations', ['menu_id' => $menu->id]);
    }

    public function test_kiosk_can_cancel_checkout_and_release_its_cart_stock(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Teh',
            'slug' => 'es-teh-cancel-checkout',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 3]);

        $this->assertSame(3, $menu->fresh()->reserved_stock);

        $this->post(route('kiosk.cart.clear'))
            ->assertRedirect(route('kiosk.index'))
            ->assertSessionHas('success', 'Pesanan dibatalkan dan stok telah dikembalikan.');

        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertDatabaseMissing('cart_reservations', ['menu_id' => $menu->id]);
    }

    public function test_changing_payment_method_cancels_midtrans_before_restoring_checkout(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Teh',
            'slug' => 'es-teh',
            'price' => 5000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-MIDTRANS-CANCEL',
            'device_id' => $device->id,
            'customer_name' => 'Siti',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'midtrans_snap_token' => 'sandbox-snap-token',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
        $this->mock(MidtransService::class)
            ->shouldReceive('cancelTransaction')
            ->once()
            ->withArgs(fn (Transaction $order): bool => $order->is($transaction));

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('id="pay-button"', false)
            ->assertSee('id="snap-cancel-order"', false)
            ->assertSee('Pilih metode pembayaran lain')
            ->assertSee('Batalkan pesanan')
            ->assertSee('window.snap.pay', false);

        $this->post(route('kiosk.orders.cancel', $transaction->order_number))
            ->assertRedirect(route('kiosk.checkout.create'))
            ->assertSessionHas('cart', [$menu->id => 1]);

        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->fresh()->payment_status);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
    }

    public function test_cancelling_digital_order_cancels_midtrans_and_does_not_restore_checkout(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Kopi',
            'slug' => 'es-kopi-cancel-order',
            'price' => 8000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-DIGITAL-CANCEL-ORDER',
            'device_id' => $device->id,
            'customer_name' => 'Dina',
            'subtotal' => 8000,
            'total' => 8000,
            'payment_method' => 'midtrans',
            'payment_channel' => 'other_qris',
            'midtrans_snap_token' => 'sandbox-snap-token',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 8000,
            'quantity' => 1,
            'subtotal' => 8000,
        ]);

        $this->mock(MidtransService::class)
            ->shouldReceive('cancelTransaction')
            ->once()
            ->withArgs(fn (Transaction $order): bool => $order->is($transaction));

        $this->post(route('kiosk.orders.cancel-order', $transaction->order_number))
            ->assertRedirect(route('kiosk.index'))
            ->assertSessionMissing('cart');

        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->fresh()->payment_status);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertDatabaseMissing('cart_reservations', ['menu_id' => $menu->id]);
    }

    public function test_two_kiosks_can_not_reserve_more_than_the_live_stock(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Sate Usus',
            'slug' => 'sate-usus-live',
            'price' => 3000,
            'stock' => 10,
        ]);
        $firstToken = Str::random(80);
        $secondToken = Str::random(80);
        Device::query()->create([
            'name' => 'Kiosk Live 1',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => null,
            'token_hash' => hash('sha256', $firstToken),
            'is_active' => true,
        ]);
        Device::query()->create([
            'name' => 'Kiosk Live 2',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => null,
            'token_hash' => hash('sha256', $secondToken),
            'is_active' => true,
        ]);

        $this->withCredentials()->withCookie('device_token', $firstToken)
            ->postJson(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 6])
            ->assertOk()
            ->assertJsonPath('stocks.'.$menu->id, 4);

        $this->assertSame(6, $menu->fresh()->reserved_stock);

        $this->withCookie('device_token', $secondToken)
            ->postJson(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(6, $menu->fresh()->reserved_stock);

        $this->withCookie('device_token', $secondToken)
            ->postJson(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 4])
            ->assertOk()
            ->assertJsonPath('stocks.'.$menu->id, 0);

        $this->assertSame(10, $menu->fresh()->reserved_stock);
        $this->assertDatabaseHas('cart_reservations', ['menu_id' => $menu->id, 'quantity' => 6]);
        $this->assertDatabaseHas('cart_reservations', ['menu_id' => $menu->id, 'quantity' => 4]);
    }

    public function test_expired_cart_reservation_releases_stock(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Kopi',
            'slug' => 'es-kopi-live',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->withCredentials()->activateDevice(Device::TYPE_KIOSK)
            ->postJson(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 3])
            ->assertOk()
            ->assertJsonPath('stocks.'.$menu->id, 7);

        $this->assertSame(3, $menu->fresh()->reserved_stock);
        $this->assertSame(1, CartReservation::query()->count());

        $this->travel(11)->minutes();

        $this->getJson(route('kiosk.stocks'))
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('stocks.'.$menu->id, 10);

        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertSame(0, CartReservation::query()->count());
    }

    public function test_local_order_stays_pending_when_midtrans_cancellation_fails(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kopi Hitam',
            'slug' => 'kopi-hitam',
            'price' => 5000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-MIDTRANS-FAIL',
            'device_id' => $device->id,
            'customer_name' => 'Budi',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'midtrans_snap_token' => 'sandbox-snap-token',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
        $this->mock(MidtransService::class)
            ->shouldReceive('cancelTransaction')
            ->once()
            ->andThrow(new \RuntimeException('Midtrans sedang tidak dapat dihubungi.'));

        $this->from(route('kiosk.orders.show', $transaction->order_number))
            ->post(route('kiosk.orders.cancel', $transaction->order_number))
            ->assertRedirect(route('kiosk.orders.show', $transaction->order_number))
            ->assertSessionHas('error', 'Midtrans sedang tidak dapat dihubungi.');

        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->fresh()->payment_status);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
    }

    public function test_admin_can_cancel_pending_order_and_return_to_transaction_history(): void
    {
        $category = Category::query()->create(['name' => 'Camilan', 'slug' => 'camilan']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kentang Goreng',
            'slug' => 'kentang-goreng',
            'price' => 5000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-ADMIN-CANCEL',
            'customer_name' => 'Wart',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($admin)
            ->post(route('admin.orders.cancel', $transaction))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success', 'Pesanan dibatalkan dan reservasi stok dilepas.');

        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->fresh()->payment_status);
        $this->assertSame(Transaction::ORDER_CANCELLED, $transaction->fresh()->order_status);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertSame(10, $menu->fresh()->stock);
    }

    public function test_valid_midtrans_webhook_is_idempotent(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Es Teh',
            'slug' => 'es-teh',
            'price' => 5000,
            'stock' => 10,
            'reserved_stock' => 2,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-WEBHOOK',
            'customer_name' => 'Siti',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'midtrans',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 2,
            'subtotal' => 10000,
        ]);
        $payload = [
            'order_id' => $transaction->order_number,
            'status_code' => '200',
            'gross_amount' => '10000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-test-id',
            'payment_type' => 'qris',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test');

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();

        $this->assertSame(8, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->fresh()->payment_status);
    }
}
