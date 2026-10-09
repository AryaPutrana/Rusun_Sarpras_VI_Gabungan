@extends('layouts.app')

@section('title', 'Riwayat Pengambilan - SI Inventory Sarpras')
@section('page-title', 'Pengambilan Barang')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Riwayat Pengambilan</span>
        <a href="{{ route('inventory.withdrawals.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Pengambilan Baru
        </a>
    </div>
    <div class="card-body">
        <!-- Search & Filter Form -->
        <form method="GET" action="{{ route('inventory.withdrawals.index') }}" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari barang / pengambil..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="date" name="start_date" class="form-control" placeholder="Dari Tanggal" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <input type="date" name="end_date" class="form-control" placeholder="Sampai Tanggal" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-1">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        @if(filled($search) || filled($startDate) || filled($endDate))
                        <a href="{{ route('inventory.withdrawals.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if(!empty($filterWarning))
            <div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle"></i> {{ $filterWarning }}
            </div>
        @endif

        <!-- Withdrawals Table -->
        @if($withdrawals->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Pengambil</th>
                            <th>Jenis Barang</th>
                            <th>Total Qty</th>
                            <th>Rusun</th>
                            <th>Tanggal</th>
                            <th>Total Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawals as $index => $withdrawal)
<tr>
                        <td>{{ $withdrawals->firstItem() + $index }}</td>
                        <td>
                            <strong>{{ $withdrawal->taken_by }}</strong><br>
                            <small class="text-muted">
                                @foreach($withdrawal->items->take(2) as $item)
                                    {{ $item->item->item_code }}
                                    @if(!$loop->last), @endif
                                @endforeach
                                @if($withdrawal->items->count() > 2)
                                    +{{ $withdrawal->items->count() - 2 }} lainnya
                                @endif
                            </small>
                        </td>
                        <td>{{ $withdrawal->items->count() }} jenis</td>
                        <td>{{ $withdrawal->total_quantity }}</td>
                        <td><span class="badge bg-info">{{ $withdrawal->rusun->code }}</span></td>
                        <td>{{ $withdrawal->taken_at ? $withdrawal->taken_at->translatedFormat('d/m/Y H:i') : '-' }}</td>
                        <td>Rp{{ number_format($withdrawal->total_value, 0, ',', '.') }}</td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('inventory.withdrawals.show', $withdrawal->id) }}" 
                                       class="btn btn-info btn-sm" 
                                       title="Lihat Detail"
                                       style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('inventory.withdrawals.edit', $withdrawal->id) }}" 
                                       class="btn btn-warning btn-sm" 
                                       title="Edit Pengambilan"
                                       style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="{{ route('inventory.withdrawals.destroy', $withdrawal->id) }}" 
                                          method="POST" 
                                          class="d-inline m-0" 
                                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengambilan ini?\n\nPengambil: {{ $withdrawal->taken_by }}\nJumlah: {{ $withdrawal->total_quantity }} unit ({{ $withdrawal->items->count() }} jenis barang)\n\nStok barang akan dikembalikan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-danger btn-sm" 
                                                title="Hapus Pengambilan"
                                                style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            {{ $withdrawals->links('pagination::bootstrap-5') }}
        @else
            <p class="text-center text-muted py-4">
                @if(filled($search))
                    Tidak ada data pengambilan yang ditemukan dengan kata kunci "{{ $search }}".
                @else
                    Belum ada data pengambilan. Silakan buat pengambilan baru.
                @endif
            </p>
        @endif
    </div>
</div>
@endsection
