<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kasir;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_midtrans_settings(): void
    {
        $this->get(route('admin.settings.midtrans.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('admin.settings.midtrans.update'), [
            'merchant_id' => 'MID-NEW',
            'is_production' => '0',
        ])->assertRedirect(route('login'));
    }

    public function test_kasir_cannot_access_or_update_midtrans_settings(): void
    {
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.settings.midtrans.edit'))
            ->assertForbidden();

        $this->put(route('admin.settings.midtrans.update'), [
            'merchant_id' => 'MID-HACK',
            'is_production' => '0',
        ])->assertForbidden();
    }

    public function test_admin_can_view_midtrans_settings_page(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->get(route('admin.settings.midtrans.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Midtrans')
            ->assertSee('Merchant ID')
            ->assertSee('Client Key')
            ->assertSee('Server Key');
    }

    public function test_admin_can_update_midtrans_settings_and_dynamically_change_config(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->put(route('admin.settings.midtrans.update'), [
                'merchant_id' => 'MERCHANT-DYNAMIC-123',
                'client_key' => 'SB-Mid-client-DYNAMIC-456',
                'server_key' => 'SB-Mid-server-DYNAMIC-789',
                'is_production' => '1',
                'enabled_payments' => ['other_qris', 'bca_va', 'bri_va'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.midtrans.edit'))
            ->assertSessionHas('status', 'Pengaturan Midtrans berhasil diperbarui.');

        $this->assertDatabaseHas('settings', [
            'key' => 'midtrans_merchant_id',
            'value' => 'MERCHANT-DYNAMIC-123',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'midtrans_client_key',
            'value' => 'SB-Mid-client-DYNAMIC-456',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'midtrans_server_key',
            'value' => 'SB-Mid-server-DYNAMIC-789',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'midtrans_is_production',
            'value' => '1',
        ]);

        $this->assertSame('MERCHANT-DYNAMIC-123', config('services.midtrans.merchant_id'));
        $this->assertSame('SB-Mid-client-DYNAMIC-456', config('services.midtrans.client_key'));
        $this->assertSame('SB-Mid-server-DYNAMIC-789', config('services.midtrans.server_key'));
        $this->assertTrue(config('services.midtrans.is_production'));
        $this->assertSame(['other_qris', 'bca_va', 'bri_va'], config('services.midtrans.enabled_payments'));
    }

    public function test_admin_can_test_midtrans_connection_with_valid_key(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/probe-connection-check/status' => Http::response([
                'status_code' => '404',
                'status_message' => "Transaction doesn't exist.",
            ], 404),
        ]);

        $admin = Admin::factory()->create();

        $response = $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->postJson(route('admin.settings.midtrans.test'), [
                'server_key' => 'SB-Mid-server-VALID-TEST-KEY',
                'is_production' => '0',
            ]);

        $response->assertOk()
            ->assertJson([
                'connected' => true,
                'status_code' => 200,
                'environment' => 'Sandbox',
            ]);
    }

    public function test_admin_can_test_midtrans_connection_with_invalid_key(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/probe-connection-check/status' => Http::response([
                'error' => 'Unauthorized',
            ], 401),
        ]);

        $admin = Admin::factory()->create();

        $response = $this->activateDevice()->actingAs($admin, 'admin')->withSession(['staff_guard' => 'admin'])
            ->postJson(route('admin.settings.midtrans.test'), [
                'server_key' => 'SB-Mid-server-INVALID-KEY',
                'is_production' => '0',
            ]);

        $response->assertOk()
            ->assertJson([
                'connected' => false,
                'status_code' => 401,
                'environment' => 'Sandbox',
            ]);
    }
}
