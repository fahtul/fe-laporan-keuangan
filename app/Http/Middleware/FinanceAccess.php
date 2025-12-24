<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FinanceAccess
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) abort(401);

        if (!$user->is_active) abort(403, 'User non-aktif');

        // kalau tidak specify roles, minimal harus punya role valid
        $role = $user->role ?? 'viewer';
        $valid = in_array($role, ['admin', 'accountant', 'viewer'], true);
        if (!$valid) abort(403, 'Role invalid');

        if (!empty($roles) && !in_array($role, $roles, true)) {
            abort(403, 'Tidak punya akses');
        }

        return $next($request);
    }
}
