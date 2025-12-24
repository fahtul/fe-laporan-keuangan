<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    private function ensureAdmin(): void
    {
        // pakai role string yang kamu buat: admin/accountant/viewer
        abort_unless(auth()->user()?->role === 'admin', 403, 'Admin only');
    }

    public function index(Request $request): View
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->ensureAdmin();

        // cegah admin mematikan dirinya sendiri (optional tapi recommended)
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $user->is_active = ! (bool) $user->is_active;
        $user->save();

        return back()->with('success', "User {$user->email} sekarang " . ($user->is_active ? 'AKTIF' : 'NON-AKTIF'));
    }
}
