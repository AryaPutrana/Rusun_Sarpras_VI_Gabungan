@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Dashboard Monitoring</h4>
    @if (! $isAdmin)
        <a href="{{ route('monitoring.create') }}" class="btn btn-primary">+ Input Monitoring</a>
    @else
        <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary">Lihat Histori</a>
    @endif
</div>

<form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="dari" class="form-label small mb-1">Periode Dari</label>
        <input type="date" id="dari" name="dari" value="{{ $dateDari }}" class="form-control">
    </div>
    <div class="col-auto">
        <label for="sampai" class="form-label small mb-1">Sampai</label>
        <input type="date" id="sampai" name="sampai" value="{{ $dateSampai }}" class="form-control">
    </div>
    <input type="hidden" name="bulan" value="{{ $financeBulan }}">
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Terapkan</button>
    </div>
    <div class="col-12 col-md-auto d-flex flex-wrap align-items-center gap-2 mb-1">
        <span class="text-muted small"></span>
        <a href="{{ route('dashboard', ['preset' => 'hari_ini', 'bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-secondary">Hari Ini</a>
        <a href="{{ route('dashboard', ['preset' => '7hari', 'bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-secondary">7 Hari Terakhir</a>
        <a href="{{ route('dashboard', ['preset' => 'bulan_ini', 'bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-secondary">Bulan Ini</a>
        <a href="{{ route('dashboard', ['bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>

@if ($total === 0)
    <div class="alert alert-info" role="alert">
        Belum ada data pemeriksaan. Mulai dari input pemeriksaan pertama untuk melihat status monitoring di dashboard ini.
    </div>
@endif

@if ($warningLocations->isNotEmpty())
    <div class="alert alert-danger d-flex align-items-start" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0 mt-1" viewBox="0 0 16 16">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
        </svg>
        <div>
            <strong>{{ $warningLocations->count() }} catatan pemeriksaan butuh perhatian:</strong>
            <ul class="mb-0">
                @foreach ($warningLocations as $wm)
                    <li>
                        <strong>{{ $wm->location?->nama_lokasi ?? 'Unknown' }}</strong>
                        — {{ \App\Models\WaterMonitoring::KONDISI[$wm->kondisi] ?? ucfirst($wm->kondisi) }}
                        ({{ $wm->tanggal->format('d/m/Y') }} {{ $wm->waktu->format('H:i') }})
                        <a href="{{ route('monitoring.show', $wm) }}" class="text-decoration-none">Lihat Detail</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@php
    $kondisiBadge = fn ($kondisi) => $kondisi === 'normal'
        ? 'bg-success'
        : 'bg-warning text-dark';
    $kondisiLabel = fn ($kondisi) => \App\Models\WaterMonitoring::KONDISI[$kondisi] ?? ucfirst($kondisi);
@endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Pemeriksaan</div>
                <div class="fs-4 fw-bold">{{ $total }}</div>
                <span class="badge bg-primary text-truncate d-inline-block" style="max-width:100%" title="Semua periode">{{ $isAdmin ? 'Semua lokasi' : 'Milik Anda' }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pemeriksaan Periode</div>
                <div class="fs-4 fw-bold">{{ $rangeCount }}</div>
                <span class="badge bg-info text-dark text-truncate d-inline-block" style="max-width:100%" title="{{ $rangeLabel }}">{{ $rangeLabel }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Cakupan Sesi</div>
                <div class="fs-4 fw-bold">{{ $sesiCoverage }}%</div>
                <span class="badge bg-secondary text-truncate d-inline-block" style="max-width:100%" title="Sesi terisi dari kemungkinan {{ $daysInRange * count(\App\Models\WaterMonitoring::SESI) }} per lokasi">{{ $daysInRange }} hari</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Lokasi Aktif</div>
                <div class="fs-4 fw-bold">{{ $activeLocationsCount }}</div>
                <span class="badge bg-secondary text-truncate d-inline-block" style="max-width:100%" title="Lokasi terdaftar">Terdaftar</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100 border-danger">
            <div class="card-body">
                <div class="text-muted small">Catatan Perlu Perhatian</div>
                <div class="fs-4 fw-bold text-danger">{{ $warningLocations->count() }}</div>
                <span class="badge bg-danger text-truncate d-inline-block" style="max-width:100%" title="Cek segera">Cek segera</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm h-100 border-success">
            <div class="card-body">
                <div class="text-muted small">% Normal Periode</div>
                <div class="fs-4 fw-bold text-success">{{ $normalPercent }}%</div>
                <span class="badge bg-success text-truncate d-inline-block" style="max-width:100%" title="{{ $rangeLabel }}">{{ $rangeCount > 0 ? $rangeLabel : 'Belum ada' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Rekap Kondisi {{ $rangeLabel }}</div>
            <div class="card-body">
                <div style="height: 260px">
                    <canvas id="kondisiChart"></canvas>
                </div>
                <div class="row text-center mt-3 g-2">
                    @foreach ($chartLabels as $i => $label)
                        <div class="col-4">
                            <span class="badge {{ $kondisiBadge(array_keys(\App\Models\WaterMonitoring::KONDISI)[$i]) }}">{{ $label }}</span>
                            <div class="fs-5 fw-bold mt-1">{{ $chartData[$i] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Kondisi per Lokasi {{ $rangeLabel }}</div>
            <div class="card-body">
                <div style="height: 260px">
                    <canvas id="lokasiChart"></canvas>
                </div>
                <div class="text-muted small mt-3">
                    Jumlah pemeriksaan per kondisi pada setiap lokasi dalam periode terpilih.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Status Terakhir per Lokasi</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Terakhir</th>
                            @if ($isAdmin)
                                <th scope="col">Petugas</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            @php $wm = $latestByLocation->get($location->id); @endphp
                            <tr>
                                <td>{{ $location->nama_lokasi }}</td>
                                @if ($wm)
                                    <td><span class="badge {{ $kondisiBadge($wm->kondisi) }}">{{ $kondisiLabel($wm->kondisi) }}</span></td>
                                    <td>{{ $wm->tanggal->format('d/m/Y') }} {{ $wm->waktu->format('H:i') }}</td>
                                    @if ($isAdmin)
                                        <td>{{ $wm->user?->name ?? 'Unknown' }}</td>
                                    @endif
                                @else
                                    <td><span class="badge bg-light text-secondary">Belum ada</span></td>
                                    <td>—</td>
                                    @if ($isAdmin)
                                        <td>—</td>
                                    @endif
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 4 : 3 }}" class="text-center text-muted py-4">
                                    Belum ada lokasi aktif.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>Kepatuhan Sesi Lokasi</span>
                <span class="small text-muted">{{ $rangeLabel }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Pagi</th>
                            <th scope="col">Siang</th>
                            <th scope="col">Sore</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            @php
                                $s = $sesiPerLokasi->get($location->id, collect());
                                $pagi = $s->get('pagi', 0);
                                $siang = $s->get('siang', 0);
                                $sore = $s->get('sore', 0);
                                $terisi = $pagi + $siang + $sore;
                                $mungkin = $daysInRange * count(\App\Models\WaterMonitoring::SESI);
                                $lengkap = $terisi >= $mungkin;
                            @endphp
                            <tr>
                                <td>{{ $location->nama_lokasi }}</td>
                                <td><span class="badge {{ $pagi >= $daysInRange ? 'bg-success' : 'bg-warning text-dark' }}">{{ $pagi }}/{{ $daysInRange }}</span></td>
                                <td><span class="badge {{ $siang >= $daysInRange ? 'bg-success' : 'bg-warning text-dark' }}">{{ $siang }}/{{ $daysInRange }}</span></td>
                                <td><span class="badge {{ $sore >= $daysInRange ? 'bg-success' : 'bg-warning text-dark' }}">{{ $sore }}/{{ $daysInRange }}</span></td>
                                <td>
                                    @if ($lengkap)
                                        <span class="badge bg-success">Lengkap</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Kurang {{ $mungkin - $terisi }} sesi</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada lokasi aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                Target {{ count(\App\Models\WaterMonitoring::SESI) }} sesi/hari &times; {{ $daysInRange }} hari per lokasi.
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Riwayat Pemeriksaan Terbaru</span>
        <a href="{{ route('monitoring.index') }}" class="small text-decoration-none">Lihat semua</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col">Sesi</th>
                    <th scope="col">Kondisi</th>
                    <th scope="col">Keterangan</th>
                    @if ($isAdmin)
                        <th scope="col">Petugas</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $i => $wm)
                    <tr>
                        <td>{{ $wm->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $wm->waktu->format('H:i') }}</td>
                        <td>{{ $wm->location?->nama_lokasi ?? 'Unknown' }}</td>
                        <td>{{ \App\Models\WaterMonitoring::SESI[$wm->sesi] ?? ucfirst($wm->sesi) }}</td>
                        <td><span class="badge {{ $kondisiBadge($wm->kondisi) }}">{{ $kondisiLabel($wm->kondisi) }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($wm->keterangan, 40) }}</td>
                        @if ($isAdmin)
                            <td>{{ $wm->user?->name ?? 'Unknown' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 6 }}" class="text-center text-muted py-4">
                            Belum ada data pemeriksaan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="finance-section">
    @include('partials.finance_section')
</div>

<!-- SECTION INVENTORY MATERIAL -->
<div class="inventory-section mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam"></i> Inventory Material</h5>
        <a href="{{ route('inventory.items.index') }}" class="btn btn-light btn-sm">Lihat Semua</a>
    </div>
    
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="inventory-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Total Barang</div>
                    <div class="fs-4 fw-bold text-primary">{{ $totalItems }}</div>
                    <small class="text-muted">Jenis</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="inventory-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Total Pengambilan</div>
                    <div class="fs-4 fw-bold text-success">{{ $totalWithdrawals }}</div>
                    <small class="text-muted">Transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="inventory-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Bulan Ini</div>
                    <div class="fs-4 fw-bold text-info">{{ $withdrawalsThisMonth }}</div>
                    <small class="text-muted">Pengambilan</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="inventory-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Total Nilai</div>
                    <div class="fs-4 fw-bold text-warning">Rp{{ number_format($totalValue, 0, ',', '.') }}</div>
                    <small class="text-muted">Pengambilan</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="inventory-card card shadow-sm">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-clock-history"></i> Pengambilan Terbaru</strong>
                </div>
                <div class="card-body">
                    @if($recentWithdrawals->isEmpty())
                        <p class="text-muted text-center mb-0">Belum ada pengambilan</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pengambil</th>
                                        <th>Rusun</th>
                                        <th class="text-end">Items</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentWithdrawals as $w)
                                    <tr>
                                        <td class="small">{{ $w->taken_at->format('d/m/Y') }}</td>
                                        <td class="small">{{ $w->taken_by }}</td>
                                        <td><span class="badge bg-secondary">{{ $w->rusun?->code ?? 'N/A' }}</span></td>
                                        <td class="text-end"><span class="badge bg-primary">{{ $w->items_count }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="inventory-card card shadow-sm">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-exclamation-triangle"></i> Stok Menipis</strong>
                </div>
                <div class="card-body">
                    @if($lowStockItems->isEmpty())
                        <p class="text-muted text-center mb-0">Semua stok aman</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th class="text-end">Stok</th>
                                        <th class="text-end">Min</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowStockItems as $item)
                                    <tr>
                                        <td class="small">{{ $item->item_code }}</td>
                                        <td class="small">{{ Str::limit($item->name, 20) }}</td>
                                        <td class="text-end"><span class="badge bg-danger">{{ $item->stock }}</span></td>
                                        <td class="text-end text-muted small">{{ $item->min_stock }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.inventory-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 2rem;
}
.inventory-card {
    background: rgba(255, 255, 255, 0.95);
    color: #333;
    border-radius: 6px;
    transition: transform 0.2s;
}
.inventory-card:hover {
    transform: translateY(-2px);
}
</style>
@endpush

@push('scripts')
<script>
    const ctx = document.getElementById('kondisiChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    data: @json($chartData),
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    const lokCtx = document.getElementById('lokasiChart');
    if (lokCtx) {
        new Chart(lokCtx, {
            type: 'bar',
            data: {
                labels: @json($stackLabels),
                datasets: [
                    { label: 'Normal', data: @json($stackNormal), backgroundColor: '#198754' },
                    { label: 'Debit Turun', data: @json($stackDebit), backgroundColor: '#ffc107' },
                    { label: 'Tekanan Air Kecil', data: @json($stackTekanan), backgroundColor: '#dc3545' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (! form || form.id !== 'financeForm') return;

        event.preventDefault();

        const input = form.querySelector('input[name="bulan"]');
        if (! input || ! input.value) return;

        const params = new URLSearchParams(window.location.search);
        params.set('bulan', input.value);

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn ? btn.textContent : '';

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Memuat...';
        }

        fetch('{{ route('dashboard.finance-section') }}?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) {
                if (! response.ok) throw new Error('Gagal memuat data');
                return response.text();
            })
            .then(function (html) {
                const section = document.getElementById('finance-section');
                if (section) section.innerHTML = html;

                const url = new URL(window.location.href);
                url.searchParams.set('bulan', input.value);
                window.history.replaceState({}, '', url.toString());
            })
            .catch(function (err) {
                console.error(err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
                window.location.reload();
            });
    });
</script>
@endpush