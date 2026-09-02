<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartReservation;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Kasir;
use App\Models\Menu;
use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\OrderService;
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

    public function test_admin_device_code_can_not_be_used_on_kiosk_activation_page(): void
    {
        $device = Device::query()->create([
            'name' => 'Legacy Admin Device',
            'type' => Device::TYPE_ADMIN,
            'activation_code_hash' => hash('sha256', 'ADMIN-S1-2026'),
            'is_active' => true,
        ]);

        $this->post(route('device.activate.store'), [
            'activation_code' => 'ADMIN-S1-2026',
        ])->assertSessionHasErrors('activation_code');

        $this->assertNotNull($device->fresh()->activation_code_hash);
        $this->assertNull($device->token_hash);
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
        $this->assertDatabaseHas('user', [
            'session_id' => $transaction->user_session_id,
            'name' => 'Siti',
        ]);
        $this->assertSame('Siti', $transaction->customer->name);
        $this->assertSame('Siti', $transaction->customer_name);
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

    public function test_midtrans_creation_and_cancellation_failure_keeps_order_pending_and_reservation(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman-create-failure']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kopi Susu',
            'slug' => 'kopi-susu-create-failure',
            'price' => 5000,
            'stock' => 10,
        ]);
        $midtrans = $this->mock(MidtransService::class);
        $midtrans->shouldReceive('createSnapToken')
            ->once()
            ->andThrow(new \RuntimeException('Midtrans sedang tidak dapat dihubungi.'));
        $midtrans->shouldReceive('cancelTransaction')
            ->once()
            ->andThrow(new \RuntimeException('Pembatalan Midtrans gagal.'));

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $response = $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Budi',
            'payment_method' => 'qris',
        ]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('kiosk.orders.show', $transaction->order_number))
            ->assertSessionHas('error', 'Pembayaran belum dapat dibatalkan di Midtrans. Pesanan tetap Pending dan stok tetap direservasi.');
        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->fresh()->payment_status);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
        $this->assertDatabaseCount('cart_reservations', 0);
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

        $this->assertDatabaseCount('transaksi', 0);
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

        $admin = Admin::factory()->create();
        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($admin)
            ->post(route('admin.orders.confirm-cash', $transaction))
            ->assertRedirect();

        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->fresh()->payment_status);
        $this->assertSame(8, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
    }

    public function test_kiosk_can_prepare_cash_payment_edit_without_creating_another_order(): void
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
        $originalId = $transaction->id;
        $originalOrderNumber = $transaction->order_number;
        $this->assertSame(2, $menu->fresh()->reserved_stock);

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('Pilih metode pembayaran lain')
            ->assertSee('Batalkan pesanan');

        $this->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertRedirect(route('kiosk.checkout.create'))
            ->assertSessionHas('success', 'Pesanan yang sama siap diperbarui. Silakan pilih metode pembayaran baru.')
            ->assertSessionHasInput('customer_name', 'Budi')
            ->assertSessionMissing('cart');

        $transaction->refresh();
        $this->assertSame($originalId, $transaction->id);
        $this->assertSame($originalOrderNumber, $transaction->order_number);
        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertNull($transaction->cancelled_at);
        $this->assertSame(2, $menu->fresh()->reserved_stock);
        $this->assertSame(10, $menu->fresh()->stock);

        $this->assertDatabaseCount('transaksi', 1);

        $this->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertDontSee('Metode pembayaran diganti')
            ->assertSee('id="change-payment-form"', false);

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

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_CANCELLED, $transaction->order_status);
        $this->assertNotNull($transaction->cancelled_at);
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

    public function test_changing_payment_method_keeps_the_same_guest_session(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman-session']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Wedang Jahe',
            'slug' => 'wedang-jahe-session',
            'price' => 7000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Ayu',
            'payment_method' => 'cash',
        ]);
        $first = Transaction::query()->firstOrFail();

        $this->mock(MidtransService::class)
            ->shouldReceive('createSnapToken')
            ->once()
            ->andReturn('replacement-qris-token');

        $this->post(route('kiosk.orders.change-payment', $first->order_number))
            ->assertRedirect(route('kiosk.checkout.create'));
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Ayu',
            'payment_method' => 'qris',
        ])->assertRedirect();

        $second = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->order_number, $second->order_number);
        $this->assertSame($first->user_session_id, $second->user_session_id);
        $this->assertSame(Transaction::PAYMENT_PENDING, $second->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $second->order_status);
        $this->assertSame('midtrans', $second->payment_method);
        $this->assertSame('other_qris', $second->payment_channel);
        $this->assertDatabaseCount('transaksi', 1);
        $this->assertDatabaseCount('user', 1);
    }

    public function test_another_guest_on_the_same_kiosk_cannot_mutate_an_order(): void
    {
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman-ownership']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kopi Tubruk',
            'slug' => 'kopi-tubruk-ownership',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Tamu Pertama',
            'payment_method' => 'cash',
        ]);
        $transaction = Transaction::query()->firstOrFail();

        $this->withSession(['guest_session_id' => 'guest-session-other'])
            ->get(route('kiosk.orders.show', $transaction->order_number))
            ->assertNotFound();
        $this->withSession(['guest_session_id' => 'guest-session-other'])
            ->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertNotFound();
        $this->withSession(['guest_session_id' => 'guest-session-other'])
            ->post(route('kiosk.orders.cancel-order', $transaction->order_number))
            ->assertNotFound();

        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->fresh()->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->fresh()->order_status);
    }

    public function test_full_cancellation_rotates_guest_session_for_the_next_customer(): void
    {
        $category = Category::query()->create(['name' => 'Camilan', 'slug' => 'camilan-session']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Tahu Crispy',
            'slug' => 'tahu-crispy-session',
            'price' => 6000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Dewi',
            'payment_method' => 'cash',
        ]);
        $first = Transaction::query()->firstOrFail();

        $this->post(route('kiosk.orders.cancel-order', $first->order_number))
            ->assertRedirect(route('kiosk.index'));
        $this->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Eka',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $second = Transaction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertNotSame($first->user_session_id, $second->user_session_id);
        $this->assertSame('Dewi', $first->customer_name);
        $this->assertSame('Eka', $second->customer_name);
        $this->assertDatabaseCount('user', 2);
    }

    public function test_confirmed_order_rotates_guest_session_when_kiosk_receives_status(): void
    {
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan-confirmed-session']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Sego Kucing',
            'slug' => 'sego-kucing-session',
            'price' => 5000,
            'stock' => 10,
        ]);

        $this->activateDevice(Device::TYPE_KIOSK)
            ->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Fajar',
            'payment_method' => 'cash',
        ]);
        $first = Transaction::query()->firstOrFail();
        app(OrderService::class)->confirmCash($first, Admin::factory()->create());

        $this->withCredentials()
            ->getJson(route('kiosk.orders.status', $first->order_number))
            ->assertOk()
            ->assertJsonPath('confirmed', true);
        $this->post(route('kiosk.cart.store'), ['menu_id' => $menu->id, 'quantity' => 1]);
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Gita',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $second = Transaction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($first->user_session_id, $second->user_session_id);
        $this->assertDatabaseCount('user', 2);
    }

    public function test_changing_qris_payment_replaces_midtrans_before_restoring_checkout(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $customer = $this->customer('Siti');
        $this->withSession(['guest_session_id' => $customer->session_id]);
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
            'user_session_id' => $customer->session_id,
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

        $this->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertRedirect(route('kiosk.checkout.create'))
            ->assertSessionMissing('cart');

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertNull($transaction->cancelled_at);
        $this->assertSame(1, $menu->fresh()->reserved_stock);

        $originalId = $transaction->id;
        $originalOrderNumber = $transaction->order_number;
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Siti',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $replacement = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($originalId, $replacement->id);
        $this->assertSame($originalOrderNumber, $replacement->order_number);
        $this->assertSame('cash', $replacement->payment_method);
        $this->assertSame(Transaction::PAYMENT_PENDING, $replacement->payment_status);
        $this->assertDatabaseCount('transaksi', 1);
    }

    public function test_cancelling_digital_order_cancels_midtrans_and_does_not_restore_checkout(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $customer = $this->customer('Dina');
        $this->withSession(['guest_session_id' => $customer->session_id]);
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
            'user_session_id' => $customer->session_id,
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

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_CANCELLED, $transaction->order_status);
        $this->assertNotNull($transaction->cancelled_at);
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
        $customer = $this->customer('Budi');
        $this->withSession(['guest_session_id' => $customer->session_id]);
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
            'user_session_id' => $customer->session_id,
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
            ->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertRedirect(route('kiosk.orders.show', $transaction->order_number))
            ->assertSessionHas('error', 'Midtrans sedang tidak dapat dihubungi.');

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
        $this->assertDatabaseCount('cart_reservations', 0);
    }

    public function test_changing_virtual_account_payment_can_create_another_virtual_account_order(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $customer = $this->customer('Rina VA');
        $this->withSession(['guest_session_id' => $customer->session_id]);
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan-va-replacement']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Kucing',
            'slug' => 'nasi-kucing-va-replacement',
            'price' => 6000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-VA-REPLACEMENT',
            'user_session_id' => $customer->session_id,
            'device_id' => $device->id,
            'customer_name' => 'Rina VA',
            'subtotal' => 6000,
            'total' => 6000,
            'payment_method' => 'midtrans',
            'payment_channel' => 'bca_va',
            'midtrans_transaction_id' => 'old-va-transaction',
            'midtrans_payment_type' => 'bank_transfer',
            'midtrans_bank' => 'bca',
            'midtrans_va_number' => '111111111111',
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 6000,
            'quantity' => 1,
            'subtotal' => 6000,
        ]);
        $midtrans = $this->mock(MidtransService::class);
        $midtrans->shouldReceive('cancelTransaction')
            ->once()
            ->withArgs(fn (Transaction $order): bool => $order->is($transaction));
        $midtrans->shouldReceive('createVirtualAccount')
            ->once()
            ->andReturn([
                'transaction_id' => 'replacement-va-transaction',
                'payment_type' => 'bank_transfer',
                'bank' => 'bni',
                'va_number' => '222222222222',
            ]);

        $this->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertRedirect(route('kiosk.checkout.create'))
            ->assertSessionMissing('cart');
        $this->post(route('kiosk.checkout.store'), [
            'customer_name' => 'Rina VA',
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'bni_va',
        ])->assertRedirect();

        $replacement = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($transaction->id, $replacement->id);
        $this->assertSame($transaction->order_number, $replacement->order_number);
        $this->assertSame(Transaction::PAYMENT_PENDING, $replacement->payment_status);
        $this->assertSame('bni_va', $replacement->payment_channel);
        $this->assertSame('222222222222', $replacement->midtrans_va_number);
        $this->assertDatabaseCount('transaksi', 1);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
        $this->assertSame(10, $menu->fresh()->stock);
    }

    public function test_cancel_webhook_finalizes_replacing_payment_without_cancelling_order(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'cancel-replacing-webhook']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Kopi Tubruk',
            'slug' => 'kopi-tubruk-cancel-replacing',
            'price' => 5000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-CANCEL-REPLACING',
            'user_session_id' => $this->customer('Cancel Replacing')->session_id,
            'customer_name' => 'Cancel Replacing',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_REPLACING,
            'order_status' => Transaction::ORDER_REPLACING,
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
        $payload = $this->midtransPayload($transaction, 'cancel', '200');

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertNull($transaction->cancelled_at);
        $this->assertSame('Pending', $transaction->payment_status_label);
        $this->assertSame(10, $menu->fresh()->stock);
        $this->assertSame(1, $menu->fresh()->reserved_stock);
    }

    public function test_cancel_webhook_without_replacement_intent_remains_cancelled(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-CANCEL-NORMAL',
            'user_session_id' => $this->customer('Cancel Normal')->session_id,
            'customer_name' => 'Cancel Normal',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_PENDING,
        ]);
        $payload = $this->midtransPayload($transaction, 'cancel', '200');

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_CANCELLED, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertNull($transaction->cancelled_at);
    }

    public function test_deny_and_expire_webhooks_keep_their_payment_statuses(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');

        foreach ([
            ['deny', Transaction::PAYMENT_FAILED],
            ['expire', Transaction::PAYMENT_EXPIRED],
        ] as [$status, $expectedPaymentStatus]) {
            $transaction = Transaction::query()->create([
                'order_number' => 'S1-TEST-'.strtoupper($status),
                'user_session_id' => $this->customer('Webhook '.$status)->session_id,
                'customer_name' => 'Webhook '.$status,
                'subtotal' => 5000,
                'total' => 5000,
                'payment_method' => 'midtrans',
                'payment_status' => Transaction::PAYMENT_PENDING,
            ]);

            $this->postJson(route('midtrans.notification'), $this->midtransPayload($transaction, $status, '200'))
                ->assertOk();

            $transaction->refresh();
            $this->assertSame($expectedPaymentStatus, $transaction->payment_status);
            $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
            $this->assertNull($transaction->cancelled_at);
        }
    }

    public function test_settlement_webhook_wins_replacement_race_and_commits_stock_once(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'settlement-replacing-webhook']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Susu Jahe',
            'slug' => 'susu-jahe-settlement-replacing',
            'price' => 7000,
            'stock' => 10,
            'reserved_stock' => 2,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-SETTLEMENT-REPLACING',
            'user_session_id' => $this->customer('Settlement Replacing')->session_id,
            'customer_name' => 'Settlement Replacing',
            'subtotal' => 14000,
            'total' => 14000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_REPLACING,
            'order_status' => Transaction::ORDER_REPLACING,
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 7000,
            'quantity' => 2,
            'subtotal' => 14000,
        ]);
        $payload = $this->midtransPayload($transaction, 'settlement', '200', [
            'transaction_id' => 'settlement-wins-race',
            'payment_type' => 'qris',
        ]);

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertSame('settlement-wins-race', $transaction->midtrans_transaction_id);
        $this->assertSame(8, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
    }

    public function test_payment_winning_change_request_does_not_restore_checkout(): void
    {
        $this->activateDevice(Device::TYPE_KIOSK);
        $device = Device::query()->latest('id')->firstOrFail();
        $customer = $this->customer('Payment Wins');
        $this->withSession(['guest_session_id' => $customer->session_id]);
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'payment-wins-change-request']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Teh Tarik',
            'slug' => 'teh-tarik-payment-wins',
            'price' => 8000,
            'stock' => 10,
            'reserved_stock' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-PAYMENT-WINS-CHANGE',
            'user_session_id' => $customer->session_id,
            'device_id' => $device->id,
            'customer_name' => 'Payment Wins',
            'subtotal' => 8000,
            'total' => 8000,
            'payment_method' => 'midtrans',
            'midtrans_snap_token' => 'payment-wins-token',
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
            ->andReturnUsing(function (Transaction $order): void {
                app(OrderService::class)->markPaid($order, [
                    'transaction_id' => 'payment-wins-change-request',
                    'payment_type' => 'qris',
                ]);
            });

        $this->from(route('kiosk.orders.show', $transaction->order_number))
            ->post(route('kiosk.orders.change-payment', $transaction->order_number))
            ->assertRedirect(route('kiosk.orders.show', $transaction->order_number))
            ->assertSessionHas('error', 'Metode pembayaran tidak dapat diganti karena status pesanan sudah berubah.')
            ->assertSessionMissing('cart');

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->payment_status);
        $this->assertSame(Transaction::ORDER_NEW, $transaction->order_status);
        $this->assertSame(9, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
        $this->assertDatabaseCount('cart_reservations', 0);
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
            'user_session_id' => $this->customer('Wart')->session_id,
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
        $admin = Admin::factory()->create();

        $this->mock(MidtransService::class)
            ->shouldReceive('cancelTransaction')
            ->once()
            ->withArgs(fn (Transaction $order): bool => $order->is($transaction));

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

    public function test_staff_cancellation_keeps_digital_order_pending_when_midtrans_fails(): void
    {
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-STAFF-CANCEL-FAIL',
            'user_session_id' => $this->customer('Staff Cancel')->session_id,
            'customer_name' => 'Staff Cancel',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_PENDING,
        ]);
        $admin = Admin::factory()->create();

        $this->mock(MidtransService::class)
            ->shouldReceive('cancelTransaction')
            ->once()
            ->withArgs(fn (Transaction $order): bool => $order->is($transaction))
            ->andThrow(new \RuntimeException('Midtrans sedang tidak dapat dihubungi.'));

        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($admin)
            ->post(route('admin.orders.cancel', $transaction))
            ->assertRedirect()
            ->assertSessionHas('error', 'Midtrans sedang tidak dapat dihubungi.');

        $this->assertSame(Transaction::PAYMENT_PENDING, $transaction->fresh()->payment_status);
    }

    public function test_admin_can_accept_paid_digital_order_and_is_recorded_as_actor(): void
    {
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-ACCEPT-ADMIN',
            'user_session_id' => $this->customer('Accept Admin')->session_id,
            'customer_name' => 'Accept Admin',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now(),
        ]);
        $admin = Admin::factory()->create();

        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($admin)
            ->post(route('admin.orders.accept-digital', $transaction))
            ->assertRedirect(route('admin.orders.receipt', ['transaction' => $transaction, 'auto' => 1]));

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaction->id,
            'admin_id' => $admin->id,
            'kasir_id' => null,
            'order_status' => Transaction::ORDER_PROCESSING,
        ]);
    }

    public function test_kasir_can_accept_paid_digital_order_and_is_recorded_as_actor(): void
    {
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-ACCEPT-KASIR',
            'user_session_id' => $this->customer('Accept Kasir')->session_id,
            'customer_name' => 'Accept Kasir',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now(),
        ]);
        $kasir = Kasir::factory()->create();

        $this->activateDevice(Device::TYPE_ADMIN)
            ->actingAs($kasir, 'kasir')
            ->withSession(['staff_guard' => 'kasir'])
            ->post(route('admin.orders.accept-digital', $transaction))
            ->assertRedirect(route('admin.orders.receipt', ['transaction' => $transaction, 'auto' => 1]));

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaction->id,
            'admin_id' => null,
            'kasir_id' => $kasir->id,
            'order_status' => Transaction::ORDER_PROCESSING,
        ]);
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
            'user_session_id' => $this->customer('Siti')->session_id,
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

    public function test_expire_webhook_does_not_reverse_a_paid_transaction(): void
    {
        Config::set('services.midtrans.server_key', 'SB-Mid-server-test');
        $category = Category::query()->create(['name' => 'Minuman', 'slug' => 'minuman-webhook-conflict']);
        $menu = Menu::query()->create([
            'category_id' => $category->id,
            'name' => 'Teh Hangat',
            'slug' => 'teh-hangat-webhook-conflict',
            'price' => 5000,
            'stock' => 9,
            'reserved_stock' => 0,
        ]);
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-WEBHOOK-CONFLICT',
            'user_session_id' => $this->customer('Webhook Conflict')->session_id,
            'customer_name' => 'Webhook Conflict',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'midtrans',
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now(),
        ]);
        $transaction->items()->create([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
        $payload = [
            'order_id' => $transaction->order_number,
            'status_code' => '407',
            'gross_amount' => '5000.00',
            'transaction_status' => 'expire',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test');

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();

        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->fresh()->payment_status);
        $this->assertSame(9, $menu->fresh()->stock);
        $this->assertSame(0, $menu->fresh()->reserved_stock);
    }

    public function test_admin_filter_and_detail_label_replaced_payment_without_cancellation(): void
    {
        $transaction = Transaction::query()->create([
            'order_number' => 'S1-TEST-ADMIN-REPLACED',
            'user_session_id' => $this->customer('Admin Replaced')->session_id,
            'customer_name' => 'Admin Replaced',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => Transaction::PAYMENT_REPLACED,
            'order_status' => Transaction::ORDER_REPLACED,
        ]);
        Transaction::query()->create([
            'order_number' => 'S1-TEST-ADMIN-CANCELLED-FILTER',
            'user_session_id' => $this->customer('Admin Cancelled')->session_id,
            'customer_name' => 'Admin Cancelled',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => Transaction::PAYMENT_CANCELLED,
            'order_status' => Transaction::ORDER_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $admin = Admin::factory()->create();
        $this->activateDevice(Device::TYPE_ADMIN)->actingAs($admin);

        $this->get(route('admin.orders.index', ['status' => Transaction::PAYMENT_REPLACED]))
            ->assertOk()
            ->assertSee('Metode Diganti')
            ->assertSee('S1-TEST-ADMIN-REPLACED')
            ->assertSee('Metode pembayaran diganti')
            ->assertDontSee('S1-TEST-ADMIN-CANCELLED-FILTER');

        $this->get(route('admin.orders.show', $transaction))
            ->assertOk()
            ->assertSee('Metode pembayaran diganti')
            ->assertDontSee('Dibatalkan');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('S1-TEST-ADMIN-REPLACED')
            ->assertSee('Metode pembayaran diganti');
    }

    private function customer(string $name): Customer
    {
        return Customer::query()->firstOrCreate([
            'session_id' => 'test-customer-'.Str::slug($name),
        ], [
            'name' => $name,
        ]);
    }

    private function midtransPayload(Transaction $transaction, string $status, string $statusCode, array $extra = []): array
    {
        $payload = [
            'order_id' => $transaction->order_number,
            'status_code' => $statusCode,
            'gross_amount' => number_format($transaction->total, 2, '.', ''),
            'transaction_status' => $status,
            ...$extra,
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test'
        );

        return $payload;
    }
}
