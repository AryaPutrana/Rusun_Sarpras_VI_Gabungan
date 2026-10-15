<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Tampilkan daftar pengguna.
     */
    public function index(): View
    {
        $users = User::orderByRaw(
            "CASE WHEN role = 'admin' THEN 0 ELSE 1 END"
        )
            ->orderBy('name')
            ->paginate(10);

        return view('users.index', compact('users'));
    }

    /**
     * Tampilkan form tambah pengguna.
     */
    public function create(): View
    {
        return view('users.create', ['roles' => User::ROLES]);
    }

    /**
     * Simpan pengguna baru.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('pengguna.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Tampilkan form ubah pengguna.
     */
    public function edit(User $pengguna): View
    {
        return view('users.edit', ['pengguna' => $pengguna, 'roles' => User::ROLES]);
    }

    /**
     * Perbarui pengguna.
     */
    public function update(UpdateUserRequest $request, User $pengguna): RedirectResponse
    {
        // Role is immutable after creation (badge-only in edit form)
        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $pengguna->update($data);

        return redirect()->route('pengguna.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    /**
     * Hapus pengguna.
     */
    public function destroy(User $pengguna): RedirectResponse
    {
        if ($pengguna->id === Auth::id()) {
            return redirect()->route('pengguna.index')
                ->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($pengguna->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return redirect()->route('pengguna.index')
                ->with('error', 'Tidak dapat menghapus admin terakhir.');
        }

        if ($pengguna->waterMonitorings()->exists()) {
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki data pemeriksaan.');
        }

        if ($pengguna->financeReports()->exists()) {
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki data laporan bulanan.');
        }

        // AUDIT TRAIL PROTECTION: Check stock adjustments history
        // User yang pernah melakukan penyesuaian stok tidak boleh dihapus
        // untuk menjaga integritas audit trail inventory
        if (\Illuminate\Support\Facades\DB::table('stock_adjustments')->where('user_id', $pengguna->id)->exists()) {
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat penyesuaian stok.');
        }

        $pengguna->delete();

        return redirect()->route('pengguna.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
