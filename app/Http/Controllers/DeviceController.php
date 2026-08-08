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
    public function index(Request $request): View
    {
        $devices = Device::query()
            ->orderByRaw("CASE WHEN type = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(12);

        return view('admin.devices.index', [
            'devices' => $devices,
            'currentDevice' => $request->attributes->get('device'),
            'summary' => [
                'total' => Device::query()->count(),
                'active' => Device::query()->where('is_active', true)->count(),
                'kiosks' => Device::query()->where('type', Device::TYPE_KIOSK)->where('is_active', true)->count(),
                'admins' => Device::query()->where('type', Device::TYPE_ADMIN)->where('is_active', true)->count(),
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
            'type' => ['required', Rule::in([Device::TYPE_KIOSK, Device::TYPE_ADMIN])],
        ]);

        $code = $activationCodes->generate($validated['type']);
        $device = Device::query()->create([
            ...$validated,
            'activation_code_hash' => $activationCodes->hash($code),
            'is_active' => true,
        ]);

        return redirect()->route('admin.devices.index')
            ->with('success', 'Perangkat berhasil ditambahkan.')
            ->with('device_activation', $this->activationMessage($device, $code));
    }

    public function regenerate(Request $request, Device $device, DeviceActivationCode $activationCodes): RedirectResponse
    {
        if ($this->isCurrentDevice($request, $device)) {
            return back()->with('error', 'Kode perangkat admin yang sedang digunakan tidak dapat direset.');
        }

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

    public function toggle(Request $request, Device $device, DeviceActivationCode $activationCodes): RedirectResponse
    {
        if ($this->isCurrentDevice($request, $device)) {
            return back()->with('error', 'Perangkat admin yang sedang digunakan tidak dapat dinonaktifkan.');
        }

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

    private function isCurrentDevice(Request $request, Device $device): bool
    {
        $currentDevice = $request->attributes->get('device');

        return $currentDevice instanceof Device && $currentDevice->is($device);
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
