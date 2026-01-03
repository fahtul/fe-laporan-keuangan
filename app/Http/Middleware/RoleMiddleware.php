<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $allowed = array_values(array_filter(array_map('trim', explode(',', $roles))));
        $role = (string) ($user->role ?? '');

        abort_unless(in_array($role, $allowed, true), 403);

        return $next($request);
    }
}

