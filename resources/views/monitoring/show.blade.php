@extends('layouts.app')

@section('title', 'Detail Pemeriksaan - ' . $monitoring->location->nama_lokasi)

@php
    $kondisiLabel = \App\Models\WaterMonitoring::KONDISI[$monitoring->kondisi] ?? ucfirst($monitoring->kondisi);
    $sesiLabel    = \App\Models\WaterMonitoring::SESI[$monitoring->sesi] ?? ucfirst($monitoring->sesi);
    $sesiWaktu    = \App\Models\WaterMonitoring::SESI_WAKTU[$monitoring->sesi] ?? '';
    $isNormal     = $monitoring->kondisi === 'normal';
    $statusLabel  = $isNormal ? 'NORMAL' : 'PERLU PERHATIAN';
    $badgeKondisi = $isNormal ? 'bg-success' : 'bg-warning text-dark';
    $badgeStatus  = $isNormal ? 'bg-success' : 'bg-warning text-dark';
    $isAdmin      = Auth::user() && Auth::user()->isAdmin();
    $isOwner      = Auth::id() === $monitoring->user_id;
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold">Detail Pemeriksaan</h4>
    <div class="d-flex gap-2">
        @if ($isAdmin || $isOwner)
            <a href="{{ route('monitoring.edit', $monitoring) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        @endif
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">Kembali ke Dashboard</a>
        <a href="{{ route('monitoring.index') }}" class="btn btn-sm btn-outline-secondary">Kembali ke Histori</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                {{-- Dicek pada file yang benar-benar ada, bukan hanya pada isi
                     kolom foto, supaya foto yang hilang di disk tidak muncul
                     sebagai gambar rusak. --}}
                @if ($monitoring->fotoExists())
                    <img src="{{ route('foto.monitoring', $monitoring) }}"
                         alt="Foto pemeriksaan {{ $monitoring->location->nama_lokasi }}"
                         class="img-fluid rounded w-100">
                @else
                    <div class="text-center text-muted py-5">
                        <p class="mb-0">Tidak ada foto untuk pemeriksaan ini.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Lokasi</dt>
                    <dd class="col-sm-8">{{ $monitoring->location->nama_lokasi }}</dd>

                    <dt class="col-sm-4">Tanggal</dt>
                    <dd class="col-sm-8">{{ $monitoring->tanggal->format('d/m/Y') }}</dd>

                    <dt class="col-sm-4">Waktu</dt>
                    <dd class="col-sm-8">{{ $monitoring->waktu->format('H:i') }}</dd>

                    <dt class="col-sm-4">Sesi</dt>
                    <dd class="col-sm-8">
                        {{ $sesiLabel }}
                        @if ($sesiWaktu)
                            <small class="text-muted">({{ $sesiWaktu }})</small>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Petugas</dt>
                    <dd class="col-sm-8">{{ $monitoring->user->name }}</dd>

                    <dt class="col-sm-4">Kondisi</dt>
                    <dd class="col-sm-8">
                        <span class="badge rounded-pill {{ $badgeKondisi }}">{{ $kondisiLabel }}</span>
                    </dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <span class="badge rounded-pill {{ $badgeStatus }}">{{ $statusLabel }}</span>
                    </dd>

                    <dt class="col-sm-4">Keterangan</dt>
                    <dd class="col-sm-8" style="white-space: pre-wrap;">{{ $monitoring->keterangan ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection