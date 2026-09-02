<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceActivationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $devices = Device::query()
            ->where('type', Device::TYPE_KIOSK)
            ->orderBy('name')
            ->paginate(12);

        return view('admin.devices.index', [
            'devices' => $devices,
            'summary' => [
                'total' => Device::query()->where('type', Device::TYPE_KIOSK)->count(),
                'kiosks' => Device::query()->where('type', Device::TYPE_KIOSK)->where('is_active', true)->count(),
                'connected' => Device::query()->where('type', Device::TYPE_KIOSK)->whereNotNull('token_hash')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.devices.create');
    }

    public function store(Request $request, DeviceActivationCode $activationCodes): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')],
        ]);

        $code = $activationCodes->generate(Device::TYPE_KIOSK);
        $device = Device::query()->create([
            ...$validated,
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => $activationCodes->hash($code),
            'is_active' => true,
        ]);

        return redirect()->route('admin.devices.index')
            ->with('success', 'Perangkat berhasil ditambahkan.')
            ->with('device_activation', $this->activationMessage($device, $code));
    }

    public function regenerate(Device $device, DeviceActivationCode $activationCodes): RedirectResponse
    {
        abort_unless($device->type === Device::TYPE_KIOSK, 404);

        $code = $activationCodes->generate($device->type);
        $device->update([
            'activation_code_hash' => $activationCodes->hash($code),
            'token_hash' => null,
            'is_active' => true,
            'activated_at' => null,
            'last_seen_at' => null,
            'last_ip' => null,
            'user_agent' => null,
        ]);

        return back()
            ->with('success', 'Kode aktivasi baru berhasil dibuat. Koneksi perangkat sebelumnya telah diputus.')
            ->with('device_activation', $this->activationMessage($device, $code));
    }

    public function toggle(Device $device, DeviceActivationCode $activationCodes): RedirectResponse
    {
        abort_unless($device->type === Device::TYPE_KIOSK, 404);

        if ($device->is_active) {
            $device->update([
                'is_active' => false,
                'activation_code_hash' => null,
                'token_hash' => null,
            ]);

            return back()->with('success', $device->name.' berhasil dinonaktifkan.');
        }

        $code = $activationCodes->generate($device->type);
        $device->update([
            'is_active' => true,
            'activation_code_hash' => $activationCodes->hash($code),
            'token_hash' => null,
            'activated_at' => null,
            'last_seen_at' => null,
            'last_ip' => null,
            'user_agent' => null,
        ]);

        return back()
            ->with('success', $device->name.' berhasil diaktifkan kembali.')
            ->with('device_activation', $this->activationMessage($device, $code));
    }

    /** @return array{name: string, code: string} */
    private function activationMessage(Device $device, string $code): array
    {
        return [
            'name' => $device->name,
            'code' => $code,
        ];
    }
}
