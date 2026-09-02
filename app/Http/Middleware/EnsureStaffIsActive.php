<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $request->session()->get('staff_guard');

        if (! in_array($guard, ['admin', 'kasir'], true) || ! Auth::guard($guard)->check()) {
            $guard = collect(['admin', 'kasir'])->first(
                fn (string $candidate): bool => Auth::guard($candidate)->check()
            );
        }

        if (! $guard) {
            return redirect()->guest(route('login'));
        }

        Auth::shouldUse($guard);
        $request->session()->put('staff_guard', $guard);
        $user = Auth::guard($guard)->user();
        $request->setUserResolver(fn () => $user);

        if (! $user?->is_active) {
            Auth::guard($guard)->logout();
            $request->session()->forget('staff_guard');
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Akun tidak aktif atau tidak memiliki akses staf.',
            ]);
        }

        return $next($request);
    }
}
