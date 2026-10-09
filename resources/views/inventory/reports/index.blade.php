@extends('layouts.app')

@section('title', 'Laporan - SI Inventory Sarpras')
@section('page-title', 'Laporan')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="bi bi-file-earmark-text"></i> Laporan Pengambilan Material
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('inventory.reports.index') }}" id="reportForm">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}" required>
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" required>
                </div>
                <div class="col-md-4">
                    <label for="rusun_id" class="form-label">Rusun</label>
                    <select class="form-select" id="rusun_id" name="rusun_id">
                        <option value="all" {{ $rusunId == 'all' || !$rusunId ? 'selected' : '' }}>Semua Rusun</option>
                        @foreach($rusuns as $rusun)
                            <option value="{{ $rusun->id }}" {{ $rusunId == $rusun->id ? 'selected' : '' }}>
                                {{ $rusun->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="report-actions mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Tampilkan Laporan
                </button>
                <a href="{{ route('inventory.reports.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
                @if($startDate && $endDate)
                    <a href="{{ route('inventory.reports.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'rusun_id' => $rusunId]) }}" 
                       class="btn btn-danger" target="_blank" rel="noopener">
                        <i class="bi bi-file-pdf"></i> Export PDF
                    </a>
                    <a href="{{ route('inventory.reports.excel', ['start_date' => $startDate, 'end_date' => $endDate, 'rusun_id' => $rusunId]) }}" 
                       class="btn btn-success">
                        <i class="bi bi-file-excel"></i> Export Excel
                    </a>
                @endif
            </div>
        </form>

        @if(!empty($filterWarning))
            <div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle"></i> {{ $filterWarning }}
            </div>
        @endif

        <hr>

        <!-- Report Content -->
        @if($startDate && $endDate)
            <div id="reportContent">
                <div class="text-center mb-4">
                    <h4>LAPORAN PENGAMBILAN MATERIAL</h4>
                    <p class="mb-0">
                        <strong>Periode:</strong> {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                    </p>
                    <p>
                        <strong>Rusun:</strong> {{ $rusunName }}
                    </p>
                </div>

                @if($transactions->count() > 0)
                    <p class="text-muted small d-md-none mb-2">
                        <i class="bi bi-arrow-left-right"></i> Geser tabel ke samping untuk melihat semua kolom.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover report-table">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>No. Transaksi</th>
                                    <th>Pengambil</th>
                                    <th>Tanggal Ambil</th>
                                    <th>Rusun</th>
                                    <th>Daftar Barang</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $index => $transaction)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $transaction->withdrawal->id }}</td>
                                    <td>{{ $transaction->withdrawal->taken_by }}</td>
                                    <td>{{ $transaction->withdrawal->taken_at ? $transaction->withdrawal->taken_at->translatedFormat('d/m/Y H:i') : '-' }}</td>
                                    <td>{{ $transaction->withdrawal->rusun?->name ?? '-' }}</td>
                                    <td>
                                        @foreach($transaction->lines as $line)
                                        <div class="item-line">
                                            @if($line['item'])
                                                <img src="{{ $line['item']->photoUrl() }}"
                                                     alt="{{ $line['name'] }}"
                                                     class="item-thumb"
                                                     loading="lazy">
                                            @endif
                                            <span>
                                                {{ $line['code'] }} - {{ $line['name'] }}
                                                ({{ number_format($line['quantity'], 0, ',', '.') }} {{ $line['unit'] }}
                                                @ Rp{{ number_format($line['unit_price'], 0, ',', '.') }})
                                            </span>
                                        </div>
                                        @endforeach
                                    </td>
                                    <td class="text-end">{{ $transaction->total_quantity }}</td>
                                    <td class="text-end">Rp{{ number_format($transaction->total_value, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="6" class="text-end">TOTAL ({{ $totalTransactions }} transaksi)</th>
                                    <th class="text-end">{{ $totalQuantity }}</th>
                                    <th class="text-end">Rp{{ number_format($totalValue, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="card bg-light mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Ringkasan Laporan</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Total Transaksi:</strong> {{ $totalTransactions }} transaksi</p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Total Barang Diambil:</strong> {{ $totalQuantity }} barang</p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Total Nilai:</strong> Rp{{ number_format($totalValue, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> Tidak ada data pengambilan pada periode yang dipilih.
                    </div>
                @endif
            </div>
        @else
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle"></i> Silakan pilih periode tanggal untuk menampilkan laporan.
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
/* =========================================================
   LAPORAN - RESPONSIF HP
   ========================================================= */

/* Tombol aksi: membungkus dengan rapi, tidak lagi meluber keluar layar */
.report-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

/* Cegah kolom tabel saling remuk; tabel digeser horizontal */
.report-table {
    min-width: 900px;
}

/* Daftar barang: thumbnail di samping teks, tetap satu baris per barang */
.item-line {
    display: flex;
    align-items: center;
    gap: 8px;
}

.item-line + .item-line {
    margin-top: 4px;
}

.item-thumb {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    object-fit: cover;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #fff;
}

@media (max-width: 768px) {
    .report-actions .btn {
        font-size: 13px;
    }

    .report-table {
        font-size: 12px;
    }

    .report-table th,
    .report-table td {
        padding: 0.4rem 0.5rem;
        white-space: nowrap;
    }

    .report-table tfoot th,
    .report-table tfoot td {
        white-space: normal;
    }
}

@media (max-width: 576px) {
    /* Tombol memenuhi lebar layar pada HP kecil */
    .report-actions .btn {
        flex: 1 1 auto;
    }

    .report-table {
        min-width: 760px;
        font-size: 11.5px;
    }

    .report-table th,
    .report-table td {
        padding: 0.35rem 0.4rem;
    }
}

@media print {
    .sidebar, .navbar-custom, .btn, form, .card-header {
        display: none !important;
    }
    .content-wrapper {
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: none !important;
    }
    body {
        background: white !important;
    }
}
</style>
@endpush
