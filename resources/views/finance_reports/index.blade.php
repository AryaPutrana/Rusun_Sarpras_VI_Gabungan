@extends('layouts.app')

@section('title', 'Laporan Bulanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Laporan Bulanan Air</h4>
    <a href="{{ route('keuangan.pdf', ['bulan' => $bulan]) }}" class="btn btn-success">Export PDF</a>
</div>

<form method="GET" action="{{ route('keuangan.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="bulan" class="form-label small mb-1">Bulan Laporan</label>
        <input type="month" id="bulan" name="bulan" value="{{ $bulan }}" class="form-control">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Cari</button>
        <a href="{{ route('keuangan.index') }}" class="btn btn-outline-secondary ms-1">Reset</a>
    </div>
</form>

<div class="alert alert-light border mb-3 d-flex flex-wrap gap-3 align-items-center">
    <span>Total data bulan <strong>{{ $bulanLabel }}</strong>: <span class="badge bg-primary">{{ $total }}</span></span>
    @foreach ($conditionLabels as $value => $label)
        <span class="small"><strong>{{ $label }}:</strong> <span class="badge bg-secondary">{{ $summary[$value] }}</span></span>
    @endforeach
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Tanggal Input</th>
                    <th scope="col">Keterangan Dropdown</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Foto</th>
                    <th scope="col">Petugas</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $i => $report)
                    <tr>
                        <td>{{ $reports->firstItem() + $i }}</td>
                        <td>{{ $report->tanggal->format('d/m/Y') }}</td>
                        <td>
                            @php
                                $kondisi = $conditionLabels[$report->kondisi] ?? ucfirst($report->kondisi);
                                $badge = $report->kondisi === 'normal' ? 'bg-success' : 'bg-warning text-dark';
                            @endphp
                            <span class="badge {{ $badge }}">{{ $kondisi }}</span>
                        </td>
                        <td>{{ $report->location->nama_lokasi ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($report->keterangan, 60) }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#fotoModal"
                                    data-foto-src="{{ route('foto.finance', $report) }}">
                                Lihat
                            </button>
                        </td>
                        <td>{{ $report->user->name }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('keuangan.edit', [$report, 'bulan' => $bulan]) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                            <form action="{{ route('keuangan.destroy', $report) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus data ini? Tindakan tidak dapat dibatalkan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada data untuk bulan ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($reports->hasPages())
        <div class="card-footer">{{ $reports->links() }}</div>
    @endif
</div>
@endsection