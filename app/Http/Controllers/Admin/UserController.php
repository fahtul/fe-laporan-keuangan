<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    private function roleOptions(): array
    {
        return ['admin', 'accountant', 'viewer'];
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $roles = $this->roleOptions();

        return view('admin.users.index', compact('users', 'q', 'roles'));
    }

    public function create(): View
    {
        $roles = $this->roleOptions();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $roles = $this->roleOptions();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:' . implode(',', $roles)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plainPassword = Str::random(12);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->is_active = (bool) ($validated['is_active'] ?? true);
        $user->email_verified_at = now();
        $user->password = Hash::make($plainPassword);
        $user->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'User berhasil dibuat.')
            ->with('generated_password', $plainPassword);
    }

    public function edit(User $user): View
    {
        $roles = $this->roleOptions();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $roles = $this->roleOptions();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:' . implode(',', $roles)],
        ]);

        $newRole = (string) $validated['role'];
        $currentRole = (string) ($user->role ?? 'viewer');

        if ($currentRole === 'admin' && $newRole !== 'admin') {
            $adminCount = User::query()->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Tidak bisa menurunkan role admin terakhir.');
            }
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $newRole;
        $user->save();

        return back()->with('success', 'User berhasil diupdate.');
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->id === auth()->id() && (bool) $user->is_active === true) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $user->is_active = ! (bool) $user->is_active;
        $user->save();

        return back()->with('success', "Status user {$user->email}: " . ($user->is_active ? 'AKTIF' : 'NON-AKTIF'));
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $plainPassword = Str::random(12);

        $user->password = Hash::make($plainPassword);
        $user->setRememberToken(Str::random(60));
        $user->save();

        return back()
            ->with('success', 'Password berhasil di-reset.')
            ->with('generated_password', $plainPassword);
    }
}

