<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceActivationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeviceActivationController extends Controller
{
    public function create(): View
    {
        return view('device.activate');
    }

    public function store(Request $request, DeviceActivationCode $activationCodes): RedirectResponse
    {
        $validated = $request->validate([
            'activation_code' => ['required', 'string', 'max:100'],
        ]);

        $activation = DB::transaction(function () use ($activationCodes, $request, $validated): ?array {
            $device = Device::query()
                ->where('activation_code_hash', $activationCodes->hash($validated['activation_code']))
                ->where('type', Device::TYPE_KIOSK)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $device) {
                return null;
            }

            $token = Str::random(80);
            $device->update([
                'activation_code_hash' => null,
                'token_hash' => hash('sha256', $token),
                'activated_at' => now(),
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);

            return compact('device', 'token');
        });

        if (! $activation) {
            return back()->withErrors(['activation_code' => 'Kode aktivasi perangkat tidak valid.']);
        }

        /** @var Device $device */
        $device = $activation['device'];
        $token = $activation['token'];

        $destination = route('kiosk.index');

        return redirect($destination)->withCookie(cookie(
            'device_token',
            $token,
            60 * 24 * 365 * 5,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'strict',
        ));
    }
}
