<div class="card shadow-sm border-success">
    <div class="card-header bg-success text-white fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Laporan Bulanan (Input Keuangan) — {{ $financeBulanLabel }}</span>
        <form method="GET" action="{{ route('dashboard') }}" id="financeForm" class="d-flex flex-wrap align-items-center gap-2">
            @foreach (array_diff(array_keys(request()->query()), ['bulan']) as $key)
                <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
            @endforeach
            <input type="month" id="financeBulan" name="bulan" value="{{ $financeBulan }}" class="form-control form-control-sm" style="width: auto;">
            <button type="submit" class="btn btn-sm btn-light">Terapkan</button>
            @if ($isAdmin)
                <a href="{{ route('keuangan.index', ['bulan' => $financeBulan]) }}" class="btn btn-sm btn-light">Lihat Laporan</a>
                <a href="{{ route('keuangan.pdf', ['bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-light">Export PDF</a>
            @else
                <a href="{{ route('keuangan.create') }}" class="btn btn-sm btn-light">+ Input Bulanan</a>
                <a href="{{ route('keuangan.riwayat', ['bulan' => $financeBulan]) }}" class="btn btn-sm btn-outline-light">Riwayat Saya</a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="row text-center g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">Total Input</div>
                <div class="fs-4 fw-bold">{{ $financeTotal }}</div>
                <span class="badge bg-success">{{ $isAdmin ? 'Semua petugas' : 'Milik Anda' }}</span>
            </div>
            @foreach ($financeKondisiLabels as $value => $label)
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold">{{ $financeCounts[$value] }}</div>
                    <span class="badge {{ $value === 'normal' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $value === 'normal' ? 'Stabil' : 'Perlu cek' }}</span>
                </div>
            @endforeach
        </div>

        @if ($financeRecent->isNotEmpty())
            <hr>
            <div class="small text-muted fw-semibold mb-2">Input Terbaru {{ $financeBulanLabel }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Kondisi</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Keterangan</th>
                            @if ($isAdmin)
                                <th scope="col">Petugas</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($financeRecent as $fr)
                            <tr>
                                <td>{{ $fr->tanggal->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $fr->kondisi === 'normal' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $financeKondisiLabels[$fr->kondisi] ?? ucfirst($fr->kondisi) }}</span></td>
                                <td>{{ $fr->location->nama_lokasi ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($fr->keterangan, 50) }}</td>
                                @if ($isAdmin)
                                    <td>{{ $fr->user->name }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-muted small mt-3">Belum ada input bulanan pada {{ $financeBulanLabel }}.</div>
        @endif
    </div>
</div>