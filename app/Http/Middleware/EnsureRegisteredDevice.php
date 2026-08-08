<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredDevice
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $token = $request->cookie('device_token');
        $device = $token
            ? Device::query()->where('token_hash', hash('sha256', $token))->where('is_active', true)->first()
            : null;

        if (! $device || $device->type !== $type) {
            return redirect()->route('device.activate')->with('error', 'Aktifkan perangkat ini terlebih dahulu.');
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
