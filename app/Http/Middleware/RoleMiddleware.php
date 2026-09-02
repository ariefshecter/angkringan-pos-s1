<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Kasir;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = match (true) {
            $request->user() instanceof Admin => 'admin',
            $request->user() instanceof Kasir => 'kasir',
            default => null,
        };

        abort_unless($role && in_array($role, $roles, true), 403);

        return $next($request);
    }
}
