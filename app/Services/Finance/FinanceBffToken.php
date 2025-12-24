<?php

namespace App\Services\Finance;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class FinanceBffToken
{
    public static function make($user): string
    {
        $user->refresh(); // ✅ ambil nilai terbaru dari DB
        $now = time();

        $payload = [
            'iss' => config('finance.iss'),
            'aud' => config('finance.aud'),
            'iat' => $now,
            'exp' => $now + config('finance.ttl'),
            'jti' => (string) Str::uuid(),

            'actorId' => (string) $user->id,
            'organizationId' => (string) config('finance.organization_id'),
            'role' => self::mapRole($user),
            'isActive' => (bool) ($user->is_active ?? true),
            'fullname' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];

        return JWT::encode($payload, config('finance.jwt_key'), 'HS256');
    }

    private static function mapRole($user): string
    {
        // Sesuaikan dengan sistem role kamu
        // contoh jika pakai spatie/permission:
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('admin')) return 'admin';
            if ($user->hasRole('accountant')) return 'accountant';
            return 'viewer';
        }

        // contoh kalau ada kolom role di users:
        $role = $user->role ?? 'viewer';
        return in_array($role, ['admin', 'accountant', 'viewer']) ? $role : 'viewer';
    }
}
