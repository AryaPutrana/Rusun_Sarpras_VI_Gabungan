@extends('layouts.app')

@section('title', 'Kelola Pengguna')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Data Pengguna</h4>
    <a href="{{ route('pengguna.create') }}" class="btn btn-primary">+ Tambah Pengguna</a>
</div>

<div class="text-muted small mb-3">Role pengguna hanya dapat diatur saat menambah pengguna dan tidak dapat diubah setelahnya.</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $i => $user)
                    <tr>
                        <td>{{ $users->firstItem() + $i }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @php
                                $roleLabel = \App\Models\User::ROLES[$user->role] ?? ucfirst($user->role);
                                $badge = match ($user->role) {
                                    'admin' => 'danger',
                                    'petugas' => 'primary',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $roleLabel }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('pengguna.edit', $user) }}" class="btn btn-sm btn-warning">Edit</a>
                            <form method="POST" action="{{ route('pengguna.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
@endsection