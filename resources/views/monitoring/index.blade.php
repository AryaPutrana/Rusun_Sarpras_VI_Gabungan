@extends('layouts.app')

@section('title', $isAdmin ? 'Histori Monitoring' : 'Histori Pemeriksaan Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">{{ $isAdmin ? 'Histori Monitoring' : 'Histori Pemeriksaan Saya' }}</h4>
    @if (! $isAdmin)
        <a href="{{ route('monitoring.create') }}" class="btn btn-primary">+ Input Monitoring</a>
    @endif
</div>

<form method="GET" action="{{ route('monitoring.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="filter_lokasi" class="form-label small mb-1">Lokasi</label>
        <select id="filter_lokasi" name="lokasi" class="form-select">
            <option value="">-- Semua Lokasi --</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}" @selected(old('lokasi', $filterLokasi) == $location->id)>{{ $location->nama_lokasi }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label for="filter_tanggal" class="form-label small mb-1">Filter Tanggal</label>
        <input type="date" id="filter_tanggal" name="tanggal" value="{{ $filterTanggal }}"
               class="form-control">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Cari</button>
        @if ($filterTanggal || $filterLokasi)
            <a href="{{ route('monitoring.index') }}" class="btn btn-outline-secondary ms-1">Reset</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col">Sesi</th>
                    <th scope="col">Kondisi</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Foto</th>
                    @if ($isAdmin)
                        <th scope="col">Petugas</th>
                    @endif
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($monitorings as $i => $wm)
                    <tr>
                        <td>{{ $monitorings->firstItem() + $i }}</td>
                        <td>{{ $wm->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $wm->waktu->format('H:i') }}</td>
                        <td>{{ $wm->location->nama_lokasi }}</td>
                        <td>{{ \App\Models\WaterMonitoring::SESI[$wm->sesi] ?? ucfirst($wm->sesi) }}</td>
                        <td>
                            @php
                                $kondisi = \App\Models\WaterMonitoring::KONDISI[$wm->kondisi] ?? ucfirst($wm->kondisi);
                                $badge = $wm->kondisi === 'normal' ? 'bg-success' : 'bg-warning text-dark';
                            @endphp
                            <span class="badge {{ $badge }}">{{ $kondisi }}</span>
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($wm->keterangan, 40) }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#fotoModal"
                                    data-foto-src="{{ route('foto.monitoring', $wm) }}">
                                Lihat
                            </button>
                        </td>
                        @if ($isAdmin)
                            <td>{{ $wm->user->name }}</td>
                        @endif
                        <td class="text-nowrap">
                            <a href="{{ route('monitoring.show', $wm) }}" class="btn btn-sm btn-outline-secondary me-1">Detail</a>
                            @if ($isAdmin || $wm->user_id === Auth::user()->id)
                                <a href="{{ route('monitoring.edit', $wm) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                <form action="{{ route('monitoring.destroy', $wm) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Hapus data pemeriksaan ini? Tindakan tidak dapat dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 10 : 9 }}" class="text-center text-muted py-4">Belum ada data pemeriksaan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($monitorings->hasPages())
        <div class="card-footer">{{ $monitorings->links() }}</div>
    @endif
</div>
@endsection