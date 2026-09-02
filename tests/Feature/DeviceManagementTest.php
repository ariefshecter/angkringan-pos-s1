<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Device;
use App\Models\Kasir;
use App\Services\DeviceActivationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_device_and_receives_a_single_use_code(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.devices.store'), [
            'name' => 'Kiosk Pelanggan 2',
        ]);

        $response->assertRedirect(route('admin.devices.index'))
            ->assertSessionHas('device_activation');

        $activation = $response->getSession()->get('device_activation');
        $device = Device::query()->where('name', 'Kiosk Pelanggan 2')->firstOrFail();

        $this->assertStringStartsWith('KIOSK-', $activation['code']);
        $this->assertSame(hash('sha256', $activation['code']), $device->activation_code_hash);

        $firstActivation = $this->post(route('device.activate.store'), [
            'activation_code' => strtolower($activation['code']),
        ]);

        $firstActivation->assertRedirect(route('kiosk.index'))->assertCookie('device_token');
        $this->assertNull($device->fresh()->activation_code_hash);

        $this->post(route('device.activate.store'), [
            'activation_code' => $activation['code'],
        ])->assertSessionHasErrors('activation_code');
    }

    public function test_two_kiosks_with_different_codes_can_run_at_the_same_time(): void
    {
        $activationCodes = app(DeviceActivationCode::class);
        $firstCode = $activationCodes->generate(Device::TYPE_KIOSK);
        $first = Device::query()->create([
            'name' => 'Kiosk 1',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => $activationCodes->hash($firstCode),
            'is_active' => true,
        ]);
        $secondCode = $activationCodes->generate(Device::TYPE_KIOSK);
        $second = Device::query()->create([
            'name' => 'Kiosk 2',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => $activationCodes->hash($secondCode),
            'is_active' => true,
        ]);

        $firstResponse = $this->post(route('device.activate.store'), ['activation_code' => $firstCode]);
        $firstToken = $firstResponse->getCookie('device_token')->getValue();
        $secondResponse = $this->post(route('device.activate.store'), ['activation_code' => $secondCode]);
        $secondToken = $secondResponse->getCookie('device_token')->getValue();

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNotSame($first->fresh()->token_hash, $second->fresh()->token_hash);
        $this->withCookie('device_token', $firstToken)->get(route('kiosk.index'))->assertOk();
        $this->withCookie('device_token', $secondToken)->get(route('kiosk.index'))->assertOk();
    }

    public function test_regenerating_a_code_disconnects_the_previous_device(): void
    {
        $admin = Admin::factory()->create();
        $oldToken = Str::random(80);
        $kiosk = Device::query()->create([
            'name' => 'Kiosk Lama',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => null,
            'token_hash' => hash('sha256', $oldToken),
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.devices.regenerate', $kiosk));

        $response->assertRedirect()->assertSessionHas('device_activation');
        $this->assertNull($kiosk->fresh()->token_hash);
        $this->assertNotNull($kiosk->fresh()->activation_code_hash);
        $this->withCookie('device_token', $oldToken)
            ->get(route('kiosk.index'))
            ->assertRedirect(route('device.activate'));
    }

    public function test_admin_can_manage_kiosk_without_device_activation(): void
    {
        $admin = Admin::factory()->create();
        $kiosk = Device::query()->create([
            'name' => 'Kiosk Tanpa Device Admin',
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => hash('sha256', 'KIOSK-LAMA'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.devices.index'))
            ->assertOk()
            ->assertSee('Admin dan Kasir tidak memerlukan aktivasi perangkat');
        $this->patch(route('admin.devices.toggle', $kiosk))->assertSessionHas('success');

        $this->assertFalse($kiosk->fresh()->is_active);
    }

    public function test_cashier_can_not_manage_devices(): void
    {
        $cashier = Kasir::factory()->create();

        $this->activateDevice()->actingAs($cashier, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.devices.index'))
            ->assertForbidden();
    }
}
