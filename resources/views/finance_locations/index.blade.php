@extends('layouts.app')

@section('title', 'Lokasi Bulanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Data Lokasi Bulanan</h4>
    <a href="{{ route('lokasiBulanan.create') }}" class="btn btn-primary">+ Tambah Lokasi</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Nama Lokasi</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-center">Total Laporan</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $i => $location)
                    <tr>
                        <td>{{ $locations->firstItem() + $i }}</td>
                        <td>{{ $location->nama_lokasi }}</td>
                        <td>
                            <span class="badge bg-{{ $location->status === 'aktif' ? 'success' : 'secondary' }}">
                                {{ \App\Models\FinanceLocation::STATUS[$location->status] ?? $location->status }}
                            </span>
                        </td>
                        <td class="text-center">{{ $location->finance_reports_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('lokasiBulanan.edit', $location) }}" class="btn btn-sm btn-warning">Edit</a>
                            <form method="POST" action="{{ route('lokasiBulanan.destroy', $location) }}" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus lokasi ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada lokasi bulanan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($locations->hasPages())
        <div class="card-footer">{{ $locations->links() }}</div>
    @endif
</div>
@endsection
