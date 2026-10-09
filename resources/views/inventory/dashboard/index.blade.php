@extends('layouts.app')

@section('title', 'Dashboard - SI Inventory Sarpras')
@section('page-title', 'Dashboard')

@section('content')
<!-- Low Stock Alert -->
@if($lowStockItems->count() > 0)
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Peringatan Stok Menipis</h5>
            <p class="mb-2">Berikut barang yang stoknya sudah mencapai/di bawah minimum stok:</p>
            <ul class="mb-0">
                @foreach($lowStockItems as $item)
                <li>
                    <strong>{{ $item->name }}</strong> ({{ $item->item_code }}) - 
                    Stok: <span class="badge bg-danger">{{ $item->stock }}</span> / 
                    Min: {{ $item->min_stock }} {{ $item->unit }}
                    <a href="{{ route('inventory.items.show', $item->id) }}" class="ms-2">
                        <i class="bi bi-arrow-right-circle"></i> Lihat Detail
                    </a>
                </li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
</div>
@endif

<div class="row">
    <!-- Stat Cards -->
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted mb-2">TOTAL BARANG</h6>
                <h2 class="mb-0">{{ $totalItems }}</h2>
                <small class="text-muted">Jenis Barang</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card success">
            <div class="card-body">
                <h6 class="text-muted mb-2">TOTAL PENGAMBILAN</h6>
                <h2 class="mb-0">{{ $totalWithdrawals }}</h2>
                <small class="text-muted">Transaksi</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card warning">
            <div class="card-body">
                <h6 class="text-muted mb-2">PENGAMBILAN BULAN INI</h6>
                <h2 class="mb-0">{{ $withdrawalsThisMonth }}</h2>
                <small class="text-muted">Transaksi</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card danger">
            <div class="card-body">
                <h6 class="text-muted mb-2">TOTAL NILAI</h6>
                <h2 class="mb-0">Rp{{ number_format($totalValue, 0, ',', '.') }}</h2>
                <small class="text-muted">Nilai Pengambilan</small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Withdrawals -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Pengambilan Terbaru
            </div>
            <div class="card-body">
                @if($recentWithdrawals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Barang</th>
                                    <th>Pengambil</th>
                                    <th>Rusun</th>
                                    <th>Jenis/Qty</th>
                                    <th>Tanggal</th>
                                    <th>Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentWithdrawals as $index => $withdrawal)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @foreach($withdrawal->items->take(2) as $item)
                                            {{ $item->item->name }}
                                            @if(!$loop->last), @endif
                                        @endforeach
                                        @if($withdrawal->items->count() > 2)
                                            +{{ $withdrawal->items->count() - 2 }} lainnya
                                        @endif
                                    </td>
                                    <td>{{ $withdrawal->taken_by }}</td>
                                    <td><span class="badge bg-info">{{ $withdrawal->rusun->code }}</span></td>
                                    <td>{{ $withdrawal->items->count() }} / {{ $withdrawal->total_quantity }}</td>
                                    <td>{{ $withdrawal->taken_at ? $withdrawal->taken_at->translatedFormat('d/m/Y H:i') : '-' }}</td>
                                    <td>Rp{{ number_format($withdrawal->total_value, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center py-4">Belum ada data pengambilan.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> Grafik Transaksi (6 Bulan Terakhir)
            </div>
            <div class="card-body">
                <canvas id="transactionChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Transaction Chart - Only Withdrawals
const ctx = document.getElementById('transactionChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($months),
        datasets: [{
            label: 'Pengambilan Barang',
            data: @json($monthlyWithdrawals),
            borderColor: 'rgb(52, 152, 219)',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Tren Pengambilan Barang (6 Bulan Terakhir)'
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return value + ' transaksi';
                    }
                }
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        }
    }
});
</script>
@endpush
