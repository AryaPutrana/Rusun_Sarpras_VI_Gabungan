@extends('layouts.app')

@section('title', 'Inventory Barang - SI Inventory Sarpras')
@section('page-title', 'Inventory Barang')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-seam"></i> Data Barang</span>
        <a href="{{ route('inventory.items.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Tambah Barang
        </a>
    </div>
    <div class="card-body">
        <!-- Search Form -->
        <form method="GET" action="{{ route('inventory.items.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari ID / Nama Barang..." value="{{ $search }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Cari
                        </button>
                        @if(filled($search))
                        <a href="{{ route('inventory.items.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Reset
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <!-- Items Table -->
        @if($items->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>ID Barang</th>
                            <th>Nama Barang</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Satuan</th>
                            <th>Tgl Input</th>
                            <th>Tgl Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $index => $item)
                        <tr>
                            <td>{{ $items->firstItem() + $index }}</td>
                            <td><strong>{{ $item->item_code }}</strong></td>
                            <td>{{ $item->name }}</td>
                            <td>Rp{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td>
                                @php
                                    $status = $item->getStockStatus();
                                @endphp
                                
                                @if($status == 'empty')
                                    <span class="badge bg-danger">{{ $item->stock }}</span>
                                @elseif($status == 'low')
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle"></i> {{ $item->stock }}
                                    </span>
                                @elseif($status == 'good')
                                    <span class="badge bg-success">{{ $item->stock }}</span>
                                @else
                                    <span class="badge bg-info">{{ $item->stock }}</span>
                                @endif
                            </td>
                            <td>{{ $item->unit }}</td>
                            <td>{{ $item->created_at ? $item->created_at->translatedFormat('d M Y') : '-' }}</td>
                            <td>{{ $item->updated_at ? $item->updated_at->translatedFormat('d M Y') : '-' }}</td>
                            <td>
                                <a href="{{ route('inventory.items.show', $item->id) }}" class="btn btn-info btn-sm" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('inventory.items.edit', $item->id) }}" class="btn btn-warning btn-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete({{ $item->id }})" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="delete-form-{{ $item->id }}" action="{{ route('inventory.items.destroy', $item->id) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            {{ $items->links('pagination::bootstrap-5') }}
        @else
            <p class="text-center text-muted py-4">
                @if(filled($search))
                    Tidak ada barang yang ditemukan dengan kata kunci "{{ $search }}".
                @else
                    Belum ada data barang. Silakan tambah barang baru.
                @endif
            </p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus barang ini?')) {
        document.getElementById('delete-form-' + id).submit();
    }
}
</script>
@endpush
